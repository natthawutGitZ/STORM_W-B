import discord
import os
import asyncio
import base64
import io
import time
from datetime import datetime, timezone, timedelta
from aiohttp import web
import aiohttp
import json
import re
import redis
from services.embeds import EmbedService
from services.events import EventService, RSVPView
from services.database import DatabaseService
from services.permissions import PermissionService

BANGKOK_TZ = timezone(timedelta(hours=7))
PORT = 5000
REDIS_HOST = os.getenv('REDIS_HOST', 'redis')
r = redis.Redis(host=REDIS_HOST, port=6379, db=0, decode_responses=True)

@web.middleware
async def api_key_middleware(request, handler):
    if request.path == '/health':
        return await handler(request)
    return await handler(request)


# --- HEALTH CHECK ---

# --- PERMISSIONS API ---

async def handle_get_permissions(request):
    self = request.app['bot']
    """Get all permissions"""
    try:
        perms = PermissionService.load_permissions()
        return web.json_response({'success': True, 'permissions': perms})
    except Exception as e:
        return web.json_response({'error': str(e)}, status=500)

async def handle_save_permissions(request):
    self = request.app['bot']
    """Save permissions"""
    try:
        data = await request.json()
        success = PermissionService.save_permissions(data)
        if success:
            return web.json_response({'success': True})
        else:
            return web.json_response({'error': 'Failed to save'}, status=500)
    except Exception as e:
        return web.json_response({'error': str(e)}, status=500)

async def handle_health_check(request):
    self = request.app['bot']
    """Simple health check endpoint (no auth required)"""
    return web.json_response({
        'status': 'ok',
        'bot_ready': self.is_ready(),
        'latency_ms': round(self.latency * 1000, 2) if self.is_ready() else None
    })

# --- BOT MANAGEMENT ---

async def handle_get_bot_status(request):
    self = request.app['bot']
    """Returns bot status information"""
    try:
        uptime_seconds = int(time.time() - self.start_time)
        hours = uptime_seconds // 3600
        minutes = (uptime_seconds % 3600) // 60
        
        # Get current activity info
        activity_type = ''
        activity_text = ''
        if self.guilds and len(self.guilds) > 0:
            me = self.guilds[0].me
            if me and me.activity:
                activity = me.activity
                activity_text = activity.name if hasattr(activity, 'name') else ''
                # Map activity type to string
                if activity.type == discord.ActivityType.playing:
                    activity_type = 'playing'
                elif activity.type == discord.ActivityType.watching:
                    activity_type = 'watching'
                elif activity.type == discord.ActivityType.listening:
                    activity_type = 'listening'
                elif activity.type == discord.ActivityType.competing:
                    activity_type = 'competing'
        
        # Get current voice channel
        current_voice_channel = None
        for guild in self.guilds:
            if guild.voice_client and guild.voice_client.is_connected():
                current_voice_channel = {
                    'id': str(guild.voice_client.channel.id),
                    'name': guild.voice_client.channel.name,
                    'guild': guild.name
                }
                break
        
        status_data = {
            'online': self.is_ready(),
            'name': self.user.name if self.user else 'Unknown',
            'id': str(self.user.id) if self.user else None,
            'avatar': str(self.user.display_avatar.url) if self.user else None,
            'discriminator': self.user.discriminator if self.user else '0000',
            'guilds': len(self.guilds),
            'guild_names': [g.name for g in self.guilds],
            'uptime': f"{hours}h {minutes}m",
            'uptime_seconds': uptime_seconds,
            'status': str(self.status) if hasattr(self, 'status') else 'online',
            'latency_ms': round(self.latency * 1000, 2),
            'activity_type': self.activity_type_cache or 'playing',
            'activity_texts': self.activity_texts_cache,
            'activity_interval': self.rotate_activity_task.seconds if getattr(self, 'rotate_activity_task', None) else 15,
            'current_voice_channel': current_voice_channel
        }
        return web.json_response({'success': True, 'data': status_data})
    except Exception as e:
        print(f"Bot Status Error: {e}", flush=True)
        return web.json_response({'success': False, 'error': str(e)}, status=500)

async def handle_update_bot_settings(request):
    self = request.app['bot']
    """Update bot settings (name, avatar, status, activity)"""
    try:
        data = await request.json()
        
        # Change Bot Name (rate limited by Discord!)
        new_name = data.get('name')
        if new_name and new_name != self.user.name:
            try:
                await self.user.edit(username=new_name)
                print(f"✅ Bot name changed to: {new_name}", flush=True)
            except discord.HTTPException as e:
                print(f"⚠️ Name change failed (rate limit?): {e}", flush=True)
                return web.json_response({
                    'success': False, 
                    'error': 'Name change rate limited by Discord. Try again later.'
                }, status=429)
        
        # Change Avatar
        avatar_base64 = data.get('avatar_base64')
        if avatar_base64:
            try:
                avatar_bytes = base64.b64decode(avatar_base64)
                await self.user.edit(avatar=avatar_bytes)
                print("✅ Bot avatar changed", flush=True)
            except Exception as e:
                print(f"⚠️ Avatar change failed: {e}", flush=True)
        
        # Change Presence (Status + Activity)
        new_status = data.get('status')  # online, idle, dnd, invisible
        activity_type = data.get('activity_type')  # playing, watching, listening
        activity_texts = data.get('activity_texts', [])
        activity_interval = data.get('activity_interval', 15)
        
        # Save to redis for rotation task
        try:
            r = redis.Redis(host='redis', port=6379, db=0)
            if activity_texts and len(activity_texts) > 0:
                r.set('bot_activity_texts', json.dumps(activity_texts))
            else:
                r.delete('bot_activity_texts')
                
            if activity_type:
                r.set('bot_activity_type', activity_type)
                
            if activity_interval:
                r.set('bot_activity_interval', str(activity_interval))
        except Exception as e:
            print(f"Redis error saving bot settings: {e}", flush=True)

        status_map = {
            'online': discord.Status.online,
            'idle': discord.Status.idle,
            'dnd': discord.Status.dnd,
            'invisible': discord.Status.invisible
        }
        
        discord_status = status_map.get(new_status, discord.Status.online)
        
        # Change status first, rotation task will pick up activity next loop
        await self.change_presence(status=discord_status)
        
        # Restart rotation task with new interval to trigger immediate update
        if getattr(self, 'rotate_activity_task', None) and self.rotate_activity_task.is_running():
            try:
                self.rotate_activity_task.change_interval(seconds=max(5, int(activity_interval)))
                self.rotate_activity_task.restart()
            except Exception as e:
                print(f"Error restarting rotation task: {e}")
        
        print(f"✅ Settings updated: {new_status}, {activity_type}, {len(activity_texts)} texts", flush=True)
        
        return web.json_response({'success': True, 'message': 'Settings updated'})
        
    except Exception as e:
        print(f"Update Settings Error: {e}", flush=True)
        return web.json_response({'success': False, 'error': str(e)}, status=500)

# --- VOICE HANDLERS ---

async def handle_get_voice_channels(request):
    self = request.app['bot']
    """Returns a list of voice channels."""
    channels_data = []
    for guild in self.guilds:
        for channel in guild.voice_channels:
            if channel.permissions_for(guild.me).connect:
                channels_data.append({
                    'id': str(channel.id),
                    'name': channel.name,
                    'guild': guild.name,
                    'members': len(channel.members),
                    'position': channel.position
                })
    channels_data.sort(key=lambda x: (x['guild'], x['position']))
    return web.json_response({'success': True, 'channels': channels_data})

async def handle_join_voice(request):
    self = request.app['bot']
    """Joins a voice channel."""
    try:
        data = await request.json()
        channel_id = data.get('channel_id')
        if not channel_id:
            return web.json_response({'error': 'Missing channel_id'}, status=400)

        channel = self.get_channel(int(channel_id))
        if not channel or not isinstance(channel, discord.VoiceChannel):
            return web.json_response({'error': 'Voice channel not found'}, status=404)

        # Disconnect existing voice client first to avoid conflicts
        if channel.guild.voice_client:
            if channel.guild.voice_client.channel.id == channel.id:
                return web.json_response({'success': True, 'message': f'Already in {channel.name}'})
            await channel.guild.voice_client.disconnect(force=True)
            await asyncio.sleep(1)

        # Connect to voice channel
        vc = await channel.connect(reconnect=True, timeout=30.0)
        await channel.guild.change_voice_state(channel=channel, self_deaf=True)
        
        print(f"✅ Voice connected to {channel.name}", flush=True)
        
        return web.json_response({'success': True, 'message': f'Joined {channel.name}'})
    except Exception as e:
        print(f"Join Voice Error: {e}", flush=True)
        import traceback
        traceback.print_exc()
        return web.json_response({'error': str(e)}, status=500)

async def handle_leave_voice(request):
    self = request.app['bot']
    """Leaves voice channel(s)."""
    try:
        # Disconnect from all guilds
        count = 0
        for vc in self.voice_clients:
            await vc.disconnect()
            count += 1
        
        return web.json_response({'success': True, 'message': f'Disconnected from {count} guild(s)'})
    except Exception as e:
        print(f"Leave Voice Error: {e}")
        return web.json_response({'error': str(e)}, status=500)

# --- ROUTES ---

async def handle_get_channels(request):
    self = request.app['bot']
    """Returns a list of text and forum channels visible to the bot."""
    channels_data = []
    for guild in self.guilds:
        # Get text channels
        for channel in guild.text_channels:
            if channel.permissions_for(guild.me).send_messages:
                channels_data.append({
                    'id': str(channel.id),
                    'name': channel.name,
                    'guild': guild.name,
                    'category': channel.category.name if channel.category else 'Uncategorized',
                    'position': channel.position,
                    'type': 'text'
                })
        
        # Get forum channels (filter from all channels)
        for channel in guild.channels:
            if channel.type == discord.ChannelType.forum:
                # Check if bot can create threads in forum
                perms = channel.permissions_for(guild.me)
                if perms.send_messages_in_threads or perms.create_public_threads:
                    channels_data.append({
                        'id': str(channel.id),
                        'name': channel.name,
                        'guild': guild.name,
                        'category': channel.category.name if channel.category else 'Uncategorized',
                        'position': channel.position,
                        'type': 'forum'
                    })
    
    # Sort by position
    channels_data.sort(key=lambda x: (x['guild'], x['category'], x['position']))
    
    return web.json_response({'success': True, 'channels': channels_data})

async def handle_get_roles(request):
    self = request.app['bot']
    """Returns a list of roles from all guilds the bot is in."""
    roles_data = []
    for guild in self.guilds:
        for role in guild.roles:
            # Skip @everyone role and bot-managed roles
            if role.is_default() or role.is_bot_managed():
                continue
            roles_data.append({
                'id': str(role.id),
                'name': role.name,
                'color': f'#{role.color.value:06x}' if role.color.value else '#99aab5',
                'mentionable': role.mentionable,
                'position': role.position,
                'guild': guild.name
            })
    
    # Sort by position (higher = more important)
    roles_data.sort(key=lambda x: -x['position'])
    
    return web.json_response({'success': True, 'roles': roles_data})

async def handle_assign_role(request):
    self = request.app['bot']
    """Assign a Discord role to a user by user_id and role_id."""
    try:
        data = await request.json()
        user_id = data.get('user_id')
        role_id = data.get('role_id')

        if not user_id or not role_id:
            return web.json_response({'success': False, 'error': 'Missing user_id or role_id'}, status=400)

        user_id = int(user_id)
        role_id = int(role_id)

        for guild in self.guilds:
            member = guild.get_member(user_id)
            if not member:
                try:
                    member = await guild.fetch_member(user_id)
                except Exception:
                    continue

            if member:
                role = guild.get_role(role_id)
                if not role:
                    return web.json_response({'success': False, 'error': f'Role {role_id} not found'}, status=404)

                await member.add_roles(role, reason='Application accepted - auto role assignment')
                return web.json_response({
                    'success': True,
                    'message': f'Role {role.name} assigned to {member.display_name}'
                })

        return web.json_response({'success': False, 'error': f'User {user_id} not found in any guild'}, status=404)

    except Exception as e:
        print(f"[ERROR] handle_assign_role: {e}")
        return web.json_response({'success': False, 'error': str(e)}, status=500)

# ========== TICKET SYSTEM ==========

async def handle_ticket_panel_send(request):
    self = request.app['bot']
    """Send a ticket panel embed with an interactive button to a channel."""
    try:
        data = await request.json()
        channel_id = int(data.get('channel_id', 0))
        panel_id = data.get('panel_id')
        title = data.get('title', 'Support Ticket')
        description = data.get('description', 'Click the button below to open a ticket.')
        button_text = data.get('button_text', 'Open Ticket')
        button_color = data.get('button_color', 'green')
        button_emoji = data.get('button_emoji', '🎫')
        embed_color_str = data.get('embed_color', '#5865F2')

        channel = self.get_channel(channel_id)
        if not channel:
            return web.json_response({'success': False, 'error': 'Channel not found'}, status=404)

        # Parse color
        try:
            color_int = int(embed_color_str.replace('#', ''), 16)
        except:
            color_int = 0x5865F2

        embed = discord.Embed(
            title=title,
            description=description,
            color=color_int,
            timestamp=datetime.now()
        )
        embed.set_footer(text='Ticket System • Click the button below')

        # Button color mapping
        color_map = {
            'green': discord.ButtonStyle.green,
            'blue': discord.ButtonStyle.blurple,
            'red': discord.ButtonStyle.red,
            'grey': discord.ButtonStyle.grey,
            'gray': discord.ButtonStyle.grey,
        }
        btn_style = color_map.get(button_color, discord.ButtonStyle.green)

        view = discord.ui.View(timeout=None)
        btn = discord.ui.Button(
            label=button_text,
            style=btn_style,
            emoji=button_emoji,
            custom_id=f'ticket_open:{panel_id}'
        )
        view.add_item(btn)

        msg = await channel.send(embed=embed, view=view)

        return web.json_response({'success': True, 'message_id': str(msg.id)})

    except Exception as e:
        print(f"[ERROR] handle_ticket_panel_send: {e}")
        return web.json_response({'success': False, 'error': str(e)}, status=500)

async def handle_ticket_create(request):
    self = request.app['bot']
    """Create a new ticket channel for a user (called from interaction handler)."""
    try:
        data = await request.json()
        guild_id = int(data.get('guild_id', 0))
        user_id = int(data.get('user_id', 0))
        panel_id = data.get('panel_id')
        category_id = data.get('category_id')
        support_role_id = data.get('support_role_id')
        welcome_message = data.get('welcome_message', '')
        user_name = data.get('user_name', 'user')

        guild = self.get_guild(guild_id)
        if not guild:
            return web.json_response({'success': False, 'error': 'Guild not found'}, status=404)

        member = guild.get_member(user_id)
        if not member:
            try:
                member = await guild.fetch_member(user_id)
            except:
                return web.json_response({'success': False, 'error': 'Member not found'}, status=404)

        # Find category
        category = None
        if category_id:
            category = guild.get_channel(int(category_id))

        # Create channel with permissions
        overwrites = {
            guild.default_role: discord.PermissionOverwrite(view_channel=False),
            member: discord.PermissionOverwrite(view_channel=True, send_messages=True, read_message_history=True, attach_files=True),
            guild.me: discord.PermissionOverwrite(view_channel=True, send_messages=True, manage_channels=True, manage_messages=True),
        }

        # Add support role
        if support_role_id:
            support_role = guild.get_role(int(support_role_id))
            if support_role:
                overwrites[support_role] = discord.PermissionOverwrite(view_channel=True, send_messages=True, read_message_history=True)

        channel_name = f'ticket-{user_name.lower().replace(" ", "-")[:20]}'
        ticket_channel = await guild.create_text_channel(
            name=channel_name,
            category=category,
            overwrites=overwrites,
            reason=f'Ticket opened by {member.display_name}'
        )

        # Send welcome message
        welcome_embed = discord.Embed(
            title='🎫 Ticket Opened',
            description=welcome_message if welcome_message else f'Welcome {member.mention}! A staff member will be with you shortly.\n\nPlease describe your issue below.',
            color=0x43b581,
            timestamp=datetime.now()
        )
        welcome_embed.set_footer(text=f'Ticket by {member.display_name}')

        # Add close button
        close_view = discord.ui.View(timeout=None)
        close_btn = discord.ui.Button(
            label='Close Ticket',
            style=discord.ButtonStyle.red,
            emoji='🔒',
            custom_id=f'ticket_close:{ticket_channel.id}'
        )
        close_view.add_item(close_btn)

        await ticket_channel.send(content=member.mention, embed=welcome_embed, view=close_view)

        return web.json_response({
            'success': True,
            'channel_id': str(ticket_channel.id),
            'channel_name': ticket_channel.name
        })

    except Exception as e:
        print(f"[ERROR] handle_ticket_create: {e}")
        return web.json_response({'success': False, 'error': str(e)}, status=500)

async def fetch_channel_transcript(channel):
    """Fetch all messages from a channel and return as a structured list."""
    messages = []
    try:
        async for msg in channel.history(limit=500, oldest_first=True):
            msg_data = {
                'author': msg.author.display_name,
                'author_id': str(msg.author.id),
                'author_avatar': str(msg.author.display_avatar.url) if msg.author.display_avatar else '',
                'is_bot': msg.author.bot,
                'content': msg.content or '',
                'timestamp': msg.created_at.isoformat(),
                'attachments': [
                    {'filename': a.filename, 'url': a.url, 'content_type': a.content_type or ''}
                    for a in msg.attachments
                ],
                'embeds': [
                    {
                        'title': e.title or '',
                        'description': e.description or '',
                        'color': str(e.color) if e.color else '',
                    }
                    for e in msg.embeds
                ] if msg.embeds else []
            }
            messages.append(msg_data)
    except Exception as e:
        print(f"[ERROR] fetch_channel_transcript: {e}", flush=True)
    return messages

async def handle_ticket_close(request):
    self = request.app['bot']
    """Close/delete a ticket channel (called from admin panel)."""
    try:
        data = await request.json()
        channel_id = int(data.get('channel_id', 0))
        ticket_id = data.get('ticket_id')

        channel = self.get_channel(channel_id)

        # Save transcript before deleting
        if channel and ticket_id:
            try:
                transcript_data = await fetch_channel_transcript(channel)
                if transcript_data:
                    async with aiohttp.ClientSession() as session:
                        await session.post('http://web/admin/ticket_actions.php', data={
                            'action': 'save_transcript',
                            'ticket_id': str(ticket_id),
                            'messages': json.dumps(transcript_data, ensure_ascii=False),
                            'message_count': str(len(transcript_data))
                        })
                    print(f"📜 Saved transcript for ticket #{ticket_id} ({len(transcript_data)} messages)", flush=True)
            except Exception as te:
                print(f"[WARN] Failed to save transcript: {te}", flush=True)

        if channel:
            await channel.delete(reason='Ticket closed')

        return web.json_response({'success': True})

    except Exception as e:
        print(f"[ERROR] handle_ticket_close: {e}")
        return web.json_response({'success': False, 'error': str(e)}, status=500)

async def handle_get_categories(request):
    self = request.app['bot']
    """Get channel categories from all guilds."""
    categories = []
    for guild in self.guilds:
        for channel in guild.channels:
            if isinstance(channel, discord.CategoryChannel):
                categories.append({
                    'id': str(channel.id),
                    'name': channel.name,
                    'guild': guild.name,
                    'position': channel.position
                })
    categories.sort(key=lambda x: x['position'])
    return web.json_response({'success': True, 'categories': categories})

async def handle_get_members(request):
    self = request.app['bot']
    """Returns a list of members from all guilds for mention autocomplete."""
    members_data = []
    seen_ids = set()  # Avoid duplicates across guilds
    
    for guild in self.guilds:
        for member in guild.members:
            # Skip bots
            if member.bot:
                continue
            
            # Skip already seen users (in case of multiple guilds)
            if member.id in seen_ids:
                continue
            
            seen_ids.add(member.id)
            members_data.append({
                'id': str(member.id),
                'name': member.display_name,
                'username': member.name,
                'avatar': str(member.display_avatar.url) if member.display_avatar else None,
                'type': 'user'
            })
    
    # Sort by display name
    members_data.sort(key=lambda x: x['name'].lower())
    
    return web.json_response({'success': True, 'members': members_data})

async def handle_get_rsvp(request):
    self = request.app['bot']
    try:
        event_id = request.query.get('event_id')
        if not event_id:
            return web.json_response({'error': 'Missing event_id'}, status=400)
        
        # Try Cache first
        cache_key = f"rsvp:{event_id}"
        cached_data = r.get(cache_key)
        if cached_data:
            return web.json_response(json.loads(cached_data))

        # Query DB
        rsvps = DatabaseService.get_rsvps(event_id)
        
        # Cache for 1 minute
        r.setex(cache_key, 60, json.dumps(rsvps))
        
        return web.json_response(rsvps)
    except Exception as e:
        return web.json_response({'error': str(e)}, status=500)

async def handle_embed_request(request):
    self = request.app['bot']
    try:
        data = await request.json()
        
        # Check delivery method first
        delivery_method = data.get('delivery_method', 'channel')
        
        # Only require channel for 'channel' delivery method
        channel = None
        if delivery_method == 'channel':
            channel_id = data.get('channel_id')
            if not channel_id:
                return web.json_response({'error': 'Missing channel_id for channel delivery'}, status=400)
            
            channel = self.get_channel(int(channel_id))
            if not channel:
                return web.json_response({'error': 'Channel not found'}, status=404)

        # Handle file attachments (base64 encoded images)
        files = []
        style = data.get('style', {})
        
        # Process main image
        if style.get('image_base64'):
            try:
                image_data = base64.b64decode(style['image_base64'])
                filename = style.get('image_filename', 'image.png')
                file = discord.File(io.BytesIO(image_data), filename=filename)
                files.append(file)
                # Update embed to use attachment URL
                style['image'] = f'attachment://{filename}'
                data['style'] = style
            except Exception as e:
                print(f"Error processing image: {e}")
        
        # Process thumbnail
        if style.get('thumbnail_base64'):
            try:
                thumb_data = base64.b64decode(style['thumbnail_base64'])
                thumb_filename = style.get('thumbnail_filename', 'thumbnail.png')
                thumb_file = discord.File(io.BytesIO(thumb_data), filename=thumb_filename)
                files.append(thumb_file)
                style['thumbnail'] = f'attachment://{thumb_filename}'
                data['style'] = style
            except Exception as e:
                print(f"Error processing thumbnail: {e}")

        # Process external image (outside embed - sent as separate attachment)
        if data.get('external_image_base64'):
            try:
                ext_data = base64.b64decode(data['external_image_base64'])
                ext_filename = data.get('external_image_filename', 'external.png')
                ext_file = discord.File(io.BytesIO(ext_data), filename=ext_filename)
                files.insert(0, ext_file)  # Insert at beginning so it appears first
                print(f"External image added: {ext_filename}")
            except Exception as e:
                print(f"Error processing external image: {e}")

        # Process Branding Icons (Author & Footer)
        branding = data.get('branding', {})
        
        # Author Icon
        if branding.get('author_icon_base64'):
            try:
                author_data = base64.b64decode(branding['author_icon_base64'])
                author_filename = branding.get('author_icon_filename', 'author.png')
                author_file = discord.File(io.BytesIO(author_data), filename=author_filename)
                files.append(author_file)
                branding['author_icon'] = f'attachment://{author_filename}'
            except Exception as e:
                print(f"Error processing author icon: {e}")

        # Footer Icon
        if branding.get('footer_icon_base64'):
            try:
                footer_data = base64.b64decode(branding['footer_icon_base64'])
                footer_filename = branding.get('footer_icon_filename', 'footer.png')
                footer_file = discord.File(io.BytesIO(footer_data), filename=footer_filename)
                files.append(footer_file)
                branding['footer_icon'] = f'attachment://{footer_filename}'
            except Exception as e:
                print(f"Error processing footer icon: {e}")
        
        data['branding'] = branding

        # Use Service to build Embed
        embed = EmbedService.create_embed(data)
        
        # Message Content (outside embed)
        content = data.get('message_content')
        
        # Buttons (View)
        view = None
        buttons = data.get('buttons', [])
        components = data.get('components', [])
        
        if buttons or components:
            view = discord.ui.View()
            
            # Standard Link Buttons
            for btn in buttons:
                if btn.get('url') and btn.get('label'):
                    emoji = btn.get('emoji') if btn.get('emoji') else None
                    view.add_item(discord.ui.Button(label=btn.get('label'), url=btn.get('url'), emoji=emoji))
            
            # Components (Select Menus)
            for comp in components:
                if comp.get('type') == 'select_menu':
                    select = discord.ui.Select(
                        custom_id=comp.get('custom_id', 'default_select'),
                        placeholder=comp.get('placeholder', 'Select an option'),
                        min_values=1,
                        max_values=1
                    )
                    for opt in comp.get('options', []):
                        select.add_option(
                            label=opt['label'],
                            value=opt['value'],
                            description=opt.get('description'),
                            emoji=opt.get('emoji')
                        )
                    view.add_item(select)
        
        # Check delivery method
        delivery_method = data.get('delivery_method', 'channel')
        dm_role_id = data.get('dm_role_id', '')
        
        if delivery_method == 'channel':
            # Original behavior - send to channel
            if files:
                msg = await channel.send(content=content, embed=embed, view=view, files=files)
            else:
                msg = await channel.send(content=content, embed=embed, view=view)
                
            return web.json_response({'success': True, 'message_id': str(msg.id)})
        
        elif delivery_method == 'dm_role':
            # DM users with specific role
            if not dm_role_id:
                return web.json_response({'error': 'Missing dm_role_id for DM by Role'}, status=400)
            
            dm_count = 0
            dm_errors = 0
            
            for guild in self.guilds:
                role = guild.get_role(int(dm_role_id))
                if not role:
                    continue
                    
                for member in role.members:
                    if member.bot:
                        continue
                    try:
                        await member.send(content=content, embed=embed, view=view)
                        dm_count += 1
                        await asyncio.sleep(0.5)  # Rate limit protection
                    except discord.Forbidden:
                        dm_errors += 1
                        print(f"Cannot DM {member.name} - DMs disabled", flush=True)
                    except Exception as e:
                        dm_errors += 1
                        print(f"DM Error for {member.name}: {e}", flush=True)
            
            return web.json_response({
                'success': True, 
                'dm_count': dm_count,
                'dm_errors': dm_errors,
                'message': f'Sent DM to {dm_count} members with {dm_errors} errors'
            })
        
        elif delivery_method == 'dm_everyone':
            # DM all members in the guild
            dm_count = 0
            dm_errors = 0
            
            for guild in self.guilds:
                # Ensure we have member cache
                if not guild.chunked:
                    try:
                        await guild.chunk()
                    except:
                        pass
                
                for member in guild.members:
                    if member.bot:
                        continue
                    try:
                        await member.send(content=content, embed=embed, view=view)
                        dm_count += 1
                        await asyncio.sleep(1)  # Slower rate for mass DMs
                    except discord.Forbidden:
                        dm_errors += 1
                    except Exception as e:
                        dm_errors += 1
                        print(f"DM Everyone Error for {member.name}: {e}", flush=True)
            
            return web.json_response({
                'success': True, 
                'dm_count': dm_count,
                'dm_errors': dm_errors,
                'message': f'Sent DM to {dm_count} members with {dm_errors} errors'
            })
        
        else:
            return web.json_response({'error': f'Unknown delivery_method: {delivery_method}'}, status=400)

    except Exception as e:
        print(f"Error handling embed request: {e}")
        return web.json_response({'error': str(e)}, status=500)

async def handle_event_request(request):
    self = request.app['bot']
    try:
        data = await request.json()
        # Schema: EVENT DATA SCHEMA (structure.txt)
        
        # Destination
        visibility = data.get('visibility', {})
        channel_id = visibility.get('channel_id')
        
        if not channel_id:
            return web.json_response({'error': 'Missing visibility.channel_id'}, status=400)

        channel = self.get_channel(int(channel_id))
        if not channel:
            return web.json_response({'error': 'Channel not found'}, status=404)

        # Image handling: Use direct URL approach
        # This works with ServBay/Cloudflare Tunnel (no interstitial page)
        # The image URL should be publicly accessible
        if data.get('image'):
            print(f"Event Image: Using direct URL = {data.get('image')}", flush=True)
        else:
            print(f"Event Image: No image provided", flush=True)

        # Build Embed (Saves to DB internally via Service)
        embed = EventService.create_event_embed(data)
        print(f"Embed Image URL: {embed.image.url if embed.image else 'None'}", flush=True)
        
        # Build View (Buttons)
        event_id = data.get('event_id', 'unknown')
        view = RSVPView(event_id)
        
        # Send event message - no file attachments needed with direct URL
        msg = await channel.send(embed=embed, view=view)
        
        # Store message_id and channel_id for reminders
        DatabaseService.update_event_message_id(event_id, channel_id, msg.id)
        
        # Create a thread from the event message for discussion
        ping_role_id = data.get('ping_role_id')
        thread = None
        try:
            thread_name = data.get('title', 'Event Discussion')[:100]  # Thread name max 100 chars
            thread = await msg.create_thread(name=thread_name)
            
            # Send the notification in the thread
            if ping_role_id:
                await thread.send(f"📢 <@&{ping_role_id}> New Operations!")
                
            # Store thread_id for future use (reminders, etc.)
            DatabaseService.update_event_thread_id(event_id, thread.id)
        except Exception as thread_err:
            print(f"Thread creation error: {thread_err}", flush=True)
        
        if visibility.get('pin_message'):
            try:
                await msg.pin()
            except:
                pass

        return web.json_response({'success': True, 'message_id': str(msg.id), 'event_id': event_id, 'thread_id': str(thread.id) if thread else None})

    except Exception as e:
        print(f"Error handling event request: {e}")
        return web.json_response({'error': str(e)}, status=500)

async def handle_get_active_events(request):
    self = request.app['bot']
    try:
        events = DatabaseService.get_active_events()
        # Convert datetime objects to ISO strings if needed, though RealDictCursor usually handles timestamps well
        # But json.dumps might fail on datetime objects depending on driver.
        # Let's clean up the data just in case
        for evt in events:
            if 'start_time' in evt and evt['start_time']:
                evt['start_time'] = str(evt['start_time'])
            if 'created_at' in evt and evt['created_at']:
                evt['created_at'] = str(evt['created_at'])
        
        return web.json_response({'success': True, 'events': events})
    except Exception as e:
        print(f"Error getting active events: {e}")
        return web.json_response({'error': str(e)}, status=500)

async def handle_event_cancel(request):
    self = request.app['bot']
    try:
        data = await request.json()
        event_id = data.get('event_id')
        if not event_id:
            return web.json_response({'error': 'Missing event_id'}, status=400)
        
        success = DatabaseService.cancel_event(event_id)
        if success:
            # Optional: Find the message and update it to say [CANCELLED] or delete it
            # For now, just DB update is fine.
            return web.json_response({'success': True})
        else:
            return web.json_response({'error': 'Event not found or already cancelled'}, status=404)
    except Exception as e:
        print(f"Error cancelling event: {e}")
        return web.json_response({'error': str(e)}, status=500)

async def handle_legacy_webhook(request):
    self = request.app['bot']
    try:
        data = await request.json()
        webhook_url = data.get('webhookUrl')
        payload = data.get('payload', {})
        
        print(f"Proxy Webhook Request to: {webhook_url}")

        if not webhook_url:
            return web.json_response({'error': 'Missing webhookUrl'}, status=400)

        # Validate URL minimally
        if not webhook_url.startswith('http'):
            return web.json_response({'error': 'Invalid webhookUrl'}, status=400)

        async with aiohttp.ClientSession() as session:
            # Append wait=true to get the message object back (optional, but good for debugging)
            target_url = webhook_url
            
            async with session.post(target_url, json=payload) as resp:
                resp_text = await resp.text()
                print(f"Discord Response ({resp.status}): {resp_text}")
                
                if 200 <= resp.status < 300:
                    try:
                        return web.json_response({'success': True, 'discord_response': json.loads(resp_text) if resp_text else {}})
                    except:
                        return web.json_response({'success': True})
                else:
                    return web.json_response({'error': f"Discord Error {resp.status}", 'details': resp_text}, status=resp.status)

    except Exception as e:
        print(f"Proxy Error: {e}")
        return web.json_response({'error': str(e)}, status=500)

async def handle_embed_edit(request):
    self = request.app['bot']
    try:
        data = await request.json()
        channel_id = data.get('channel_id')
        message_id = data.get('message_id')
        
        if not channel_id or not message_id:
            return web.json_response({'error': 'Missing channel_id or message_id'}, status=400)

        channel = self.get_channel(int(channel_id))
        if not channel:
            return web.json_response({'error': 'Channel not found'}, status=404)
            
        try:
            message = await channel.fetch_message(int(message_id))
        except discord.NotFound:
            return web.json_response({'error': 'Message not found'}, status=404)

        # Handle file attachments (base64 encoded images) - Reuse logic
        files = []
        style = data.get('style', {})
        
        # Process main image
        if style.get('image_base64'):
            try:
                image_data = base64.b64decode(style['image_base64'])
                filename = style.get('image_filename', 'image.png')
                file = discord.File(io.BytesIO(image_data), filename=filename)
                files.append(file)
                style['image'] = f'attachment://{filename}'
                data['style'] = style
            except Exception as e:
                print(f"Error processing image: {e}")
        
        # Process thumbnail
        if style.get('thumbnail_base64'):
            try:
                thumb_data = base64.b64decode(style['thumbnail_base64'])
                thumb_filename = style.get('thumbnail_filename', 'thumbnail.png')
                thumb_file = discord.File(io.BytesIO(thumb_data), filename=thumb_filename)
                files.append(thumb_file)
                style['thumbnail'] = f'attachment://{thumb_filename}'
                data['style'] = style
            except Exception as e:
                print(f"Error processing thumbnail: {e}")
        
        # Branding
        branding = data.get('branding', {})
        if branding.get('author_icon_base64'):
            try:
                author_data = base64.b64decode(branding['author_icon_base64'])
                author_filename = branding.get('author_icon_filename', 'author.png')
                author_file = discord.File(io.BytesIO(author_data), filename=author_filename)
                files.append(author_file)
                branding['author_icon'] = f'attachment://{author_filename}'
            except Exception as e:
                print(f"Error processing author icon: {e}")
        
        if branding.get('footer_icon_base64'):
            try:
                footer_data = base64.b64decode(branding['footer_icon_base64'])
                footer_filename = branding.get('footer_icon_filename', 'footer.png')
                footer_file = discord.File(io.BytesIO(footer_data), filename=footer_filename)
                files.append(footer_file)
                branding['footer_icon'] = f'attachment://{footer_filename}'
            except Exception as e:
                print(f"Error processing footer icon: {e}")

        data['branding'] = branding

        # Build Embed
        embed = EmbedService.create_embed(data)
        content = data.get('message_content')
        
        # Buttons (View)
        view = None
        buttons = data.get('buttons', [])
        if buttons:
            view = discord.ui.View()
            for btn in buttons:
                 if btn.get('url') and btn.get('label'):
                    emoji = btn.get('emoji') if btn.get('emoji') else None
                    view.add_item(discord.ui.Button(label=btn.get('label'), url=btn.get('url'), emoji=emoji))

        # Edit Message
        if files:
            # If sending new files, we typically clear old attachments or replace them
            # For simplicity, we assume we are replacing the visual experience
            await message.edit(content=content, embed=embed, view=view, attachments=[], files=files)
        else:
            # Keep existing attachments if any? Or clear them?
            # If user removed image in UI, they expect it gone.
            # But UI just sends what is currently configured.
            # If UI sends no image, style['image'] is empty.
            # If we don't pass attachments=[], it keeps them.
            # If we want to strictly follow UI state, we should probably clear attachments if not provided.
            # But if image was a URL, it wasn't an attachment.
            # Let's assume we want to Replace logic.
            await message.edit(content=content, embed=embed, view=view) # attachments=[] if we want to clear?

        return web.json_response({'success': True, 'message_id': str(message.id)})

    except Exception as e:
        print(f"Error editing message: {e}")
        return web.json_response({'error': str(e)}, status=500)

# ================== Saved Messages (Message Builder) ==================

async def handle_list_messages(request):
    self = request.app['bot']
    """List all saved messages from PHP API"""
    try:
        # Forward to PHP API which has MySQL access
        php_api_url = os.getenv('PHP_API_URL', 'http://web:80')
        async with aiohttp.ClientSession() as session:
            async with session.get(f"{php_api_url}/api/messages.php?action=list") as resp:
                data = await resp.json()
                return web.json_response(data)
    except Exception as e:
        print(f"Error listing messages: {e}")
        return web.json_response({'error': str(e)}, status=500)

async def handle_get_message(request):
    self = request.app['bot']
    """Get a specific saved message"""
    try:
        msg_id = request.match_info['id']
        php_api_url = os.getenv('PHP_API_URL', 'http://web:80')
        async with aiohttp.ClientSession() as session:
            async with session.get(f"{php_api_url}/api/messages.php?action=get&id={msg_id}") as resp:
                data = await resp.json()
                return web.json_response(data)
    except Exception as e:
        print(f"Error getting message: {e}")
        return web.json_response({'error': str(e)}, status=500)

async def handle_save_message(request):
    self = request.app['bot']
    """Save a new message and optionally send to Discord"""
    try:
        data = await request.json()
        
        # If send_to_discord is true, send the message first
        message_id = None
        if data.get('send_to_discord'):
            channel_id = data.get('channel_id')
            channel = self.get_channel(int(channel_id))
            if not channel:
                return web.json_response({'error': 'Channel not found'}, status=404)
            
            # Build embed if provided
            embed = None
            embed_data = data.get('embed_data')
            if embed_data:
                try:
                    color_hex = embed_data.get('color', '#5865f2')
                    color = discord.Color.from_str(color_hex)
                except:
                    color = discord.Color.blue()
                
                embed = discord.Embed(
                    title=embed_data.get('title', ''),
                    description=embed_data.get('description', ''),
                    color=color
                )
                if embed_data.get('thumbnail'):
                    embed.set_thumbnail(url=embed_data['thumbnail'])
                if embed_data.get('image'):
                    embed.set_image(url=embed_data['image'])
                if embed_data.get('footer'):
                    embed.set_footer(text=embed_data['footer'])
                if embed_data.get('author'):
                    embed.set_author(name=embed_data['author'].get('name', ''))
            
            # Send the message
            sent_msg = await channel.send(content=data.get('content', ''), embed=embed)
            message_id = str(sent_msg.id)
            data['status'] = 'published'
        
        # Save to DB via PHP API
        data['message_id'] = message_id
        php_api_url = os.getenv('PHP_API_URL', 'http://web:80')
        async with aiohttp.ClientSession() as session:
            async with session.post(f"{php_api_url}/api/messages.php?action=save", json=data) as resp:
                result = await resp.json()
                return web.json_response(result)
                
    except Exception as e:
        print(f"Error saving message: {e}")
        return web.json_response({'error': str(e)}, status=500)

async def handle_update_message(request):
    self = request.app['bot']
    """Update an existing message and edit in Discord"""
    try:
        msg_id = request.match_info['id']
        data = await request.json()
        
        # If message_id exists and update_discord is true, edit the Discord message
        discord_message_id = data.get('message_id')
        if discord_message_id and data.get('update_discord'):
            channel_id = data.get('channel_id')
            channel = self.get_channel(int(channel_id))
            if channel:
                try:
                    discord_msg = await channel.fetch_message(int(discord_message_id))
                    
                    # Build new embed
                    embed = None
                    embed_data = data.get('embed_data')
                    if embed_data:
                        try:
                            color = discord.Color.from_str(embed_data.get('color', '#5865f2'))
                        except:
                            color = discord.Color.blue()
                        
                        embed = discord.Embed(
                            title=embed_data.get('title', ''),
                            description=embed_data.get('description', ''),
                            color=color
                        )
                        if embed_data.get('thumbnail'):
                            embed.set_thumbnail(url=embed_data['thumbnail'])
                        if embed_data.get('image'):
                            embed.set_image(url=embed_data['image'])
                        if embed_data.get('footer'):
                            embed.set_footer(text=embed_data['footer'])
                    
                    # Edit the message
                    await discord_msg.edit(content=data.get('content', ''), embed=embed)
                    print(f"✅ Edited Discord message {discord_message_id}")
                except discord.NotFound:
                    print(f"⚠️ Discord message {discord_message_id} not found")
                except discord.Forbidden:
                    print(f"⚠️ No permission to edit message {discord_message_id}")
        
        # Update in DB via PHP API
        php_api_url = os.getenv('PHP_API_URL', 'http://web:80')
        async with aiohttp.ClientSession() as session:
            async with session.post(f"{php_api_url}/api/messages.php?action=update&id={msg_id}", json=data) as resp:
                result = await resp.json()
                return web.json_response(result)
                
    except Exception as e:
        print(f"Error updating message: {e}")
        return web.json_response({'error': str(e)}, status=500)

async def handle_delete_message(request):
    self = request.app['bot']
    """Delete a saved message"""
    try:
        msg_id = request.match_info['id']
        data = await request.json() if request.body_exists else {}
        
        # Optionally delete from Discord too
        if data.get('delete_from_discord'):
            discord_message_id = data.get('message_id')
            channel_id = data.get('channel_id')
            if discord_message_id and channel_id:
                channel = self.get_channel(int(channel_id))
                if channel:
                    try:
                        discord_msg = await channel.fetch_message(int(discord_message_id))
                        await discord_msg.delete()
                        print(f"🗑️ Deleted Discord message {discord_message_id}")
                    except:
                        pass
        
        # Delete from DB via PHP API
        php_api_url = os.getenv('PHP_API_URL', 'http://web:80')
        async with aiohttp.ClientSession() as session:
            async with session.delete(f"{php_api_url}/api/messages.php?action=delete&id={msg_id}") as resp:
                result = await resp.json()
                return web.json_response(result)
                
    except Exception as e:
        print(f"Error deleting message: {e}")
        return web.json_response({'error': str(e)}, status=500)

# ================== Welcome Sound Settings ==================

async def handle_get_welcome_sound(request):
    self = request.app['bot']
    """Get current welcome sound settings"""
    try:
        # Get settings from Redis
        enabled = r.get('welcome_sound_enabled') == 'true'
        filename = r.get('welcome_sound_filename') or ''
        channel_id = r.get('welcome_sound_channel') or ''
        delay = int(r.get('welcome_sound_delay') or 2)
        
        # New fields
        message_text = r.get('welcome_message_text') or ''
        dropdown_json = r.get('welcome_dropdown_options') or '[]'
        try:
            dropdown_options = json.loads(dropdown_json)
        except:
            dropdown_options = []
        
        # Check if file exists
        sound_path = '/app/sounds/welcome.mp3'
        has_file = os.path.exists(sound_path)
        
        return web.json_response({
            'success': True,
            'data': {
                'enabled': enabled,
                'filename': filename,
                'channel_id': channel_id,
                'has_file': has_file,
                'delay': delay,
                'message_text': message_text,
                'message_text': message_text,
                'dropdown_options': dropdown_options,
                'link_buttons': json.loads(r.get('welcome_link_buttons') or '[]')
            }
        })
    except Exception as e:
        print(f"Error getting welcome sound settings: {e}")
        return web.json_response({'error': str(e)}, status=500)

async def handle_set_welcome_sound(request):
    self = request.app['bot']
    """Set welcome sound settings with MP3 file upload"""
    try:
        data = await request.json()
        print(f"DEBUG: handle_set_welcome_sound payload keys: {data.keys()}")
        print(f"DEBUG: link_buttons in payload: {data.get('link_buttons')}")
        
        enabled = data.get('enabled', False)
        channel_id = data.get('channel_id', '')
        sound_base64 = data.get('sound_base64', '')
        filename = data.get('filename', '')
        delay = data.get('delay', 2) # Default 2 seconds
        
        # New fields
        message_text = data.get('message_text', '')
        # New fields
        message_text = data.get('message_text', '')
        dropdown_options = data.get('dropdown_options', [])
        link_buttons = data.get('link_buttons', [])
        
        # Handle file upload if provided
        if sound_base64:
            try:
                # Decode base64 and save to file
                sound_data = base64.b64decode(sound_base64)
                
                # Ensure sounds directory exists
                os.makedirs('/app/sounds', exist_ok=True)
                
                # Save the file
                sound_path = '/app/sounds/welcome.mp3'
                with open(sound_path, 'wb') as f:
                    f.write(sound_data)
                
                print(f"✅ Welcome sound saved: {filename} ({len(sound_data)} bytes)")
                
                # Save filename to Redis
                r.set('welcome_sound_filename', filename)
                
            except Exception as e:
                print(f"Error saving welcome sound file: {e}")
                return web.json_response({'error': f'Failed to save file: {str(e)}'}, status=500)
        
        # Check for explicitly requested deletion
        delete_file = data.get('delete_file', False)
        if delete_file:
            sound_path = '/app/sounds/welcome.mp3'
            if os.path.exists(sound_path):
                try:
                    os.remove(sound_path)
                    print("🗑️ Welcome sound file removed.")
                except Exception as e:
                     print(f"Error removing file: {e}")
            
            # Also clear filename from Redis
            r.delete('welcome_sound_filename')
            filename = '' # Update local var so it doesn't get re-set below if flow continues
        
        # Save settings to Redis
        r.set('welcome_sound_enabled', 'true' if enabled else 'false')
        r.set('welcome_sound_channel', channel_id)
        r.set('welcome_sound_delay', str(delay))
        
        # Save new fields
        r.set('welcome_message_text', message_text)
        r.set('welcome_message_text', message_text)
        r.set('welcome_dropdown_options', json.dumps(dropdown_options))
        r.set('welcome_link_buttons', json.dumps(link_buttons))
         
        
        return web.json_response({
            'success': True,
            'message': 'Welcome sound settings updated'
        })
    except Exception as e:
        print(f"Error setting welcome sound: {e}")
        return web.json_response({'error': str(e)}, status=500)


# --- FEED API HANDLERS ---

async def handle_get_feed_settings(request):
    self = request.app['bot']
    """Get feed channel settings"""
    try:
        # Return cached monitored channels
        channels = self.get_feed_monitored_channels()
        
        # Get settings from Redis
        settings_raw = r.get('feed_settings')
        settings = json.loads(settings_raw) if settings_raw else {}
        
        return web.json_response({
            'success': True,
            'monitored_channels': channels,
            'settings': settings
        })
    except Exception as e:
        return web.json_response({'error': str(e)}, status=500)

async def handle_save_feed_settings(request):
    self = request.app['bot']
    """Save feed channel settings"""
    try:
        data = await request.json()
        
        # Validate data
        section = data.get('section')
        channel_id = data.get('channel_id')
        enabled = data.get('enabled', True)
        
        if not section or not channel_id:
            return web.json_response({'error': 'Missing section or channel_id'}, status=400)
        
        # Get channel name for display
        channel = self.get_channel(int(channel_id))
        channel_name = channel.name if channel else 'Unknown'
        
        # Load existing settings
        settings_raw = r.get('feed_settings')
        settings = json.loads(settings_raw) if settings_raw else {}
        
        # Update settings for this section
        settings[section] = {
            'channel_id': channel_id,
            'channel_name': channel_name,
            'enabled': enabled
        }
        
        # Save to Redis
        r.set('feed_settings', json.dumps(settings))
        
        # Update monitored channels list
        monitored = [s['channel_id'] for s in settings.values() if s.get('enabled')]
        r.set('feed_monitored_channels', json.dumps(monitored))
        
        print(f"📡 Feed settings updated: {section} -> #{channel_name}")
        
        return web.json_response({'success': True, 'channel_name': channel_name})
    except Exception as e:
        print(f"Feed settings error: {e}")
        return web.json_response({'error': str(e)}, status=500)

async def handle_get_feed_messages(request):
    self = request.app['bot']
    """Get messages from feed queue for a section"""
    try:
        section = request.query.get('section', '')
        limit = int(request.query.get('limit', 20))
        
        # Get channel for this section
        settings_raw = r.get('feed_settings')
        settings = json.loads(settings_raw) if settings_raw else {}
        
        section_config = settings.get(section, {})
        if not section_config.get('enabled'):
            return web.json_response({'success': True, 'messages': [], 'enabled': False})
        
        target_channel = section_config.get('channel_id')
        
        # Get messages from queue and filter by channel
        all_messages = r.lrange('feed_messages_queue', 0, 999)
        messages = []
        
        for msg_raw in all_messages:
            try:
                msg = json.loads(msg_raw)
                if msg.get('channel_id') == target_channel:
                    messages.append(msg)
                    if len(messages) >= limit:
                        break
            except:
                continue
        
        return web.json_response({
            'success': True,
            'messages': messages,
            'enabled': True,
            'channel_name': section_config.get('channel_name', '')
        })
    except Exception as e:
        return web.json_response({'error': str(e)}, status=500)

async def handle_lookup_user(request):
    self = request.app['bot']
    """Lookup Discord user by username and return their ID for @mention"""
    try:
        username = request.query.get('username', '').strip()
        
        if not username:
            return web.json_response({'success': False, 'error': 'Missing username parameter'})
        
        # Clean username (remove @ prefix if present)
        if username.startswith('@'):
            username = username[1:]
        
        # Remove discriminator if present (legacy format like User#1234)
        if '#' in username:
            username = username.split('#')[0]
        
        username_lower = username.lower()
        found_user = None
        
        # Search in all guilds
        for guild in self.guilds:
            for member in guild.members:
                # Match by username (without discriminator)
                if member.name.lower() == username_lower:
                    found_user = member
                    break
                # Match by display name (nickname)
                if member.display_name.lower() == username_lower:
                    found_user = member
                    break
                # Match by global name (new Discord display name)
                if hasattr(member, 'global_name') and member.global_name:
                    if member.global_name.lower() == username_lower:
                        found_user = member
                        break
            if found_user:
                break
        
        if found_user:
            return web.json_response({
                'success': True,
                'found': True,
                'user_id': str(found_user.id),
                'username': found_user.name,
                'display_name': found_user.display_name,
                'mention': f'<@{found_user.id}>'
            })
        else:
            return web.json_response({
                'success': True,
                'found': False,
                'message': f'User "{username}" not found in any server'
            })
            
    except Exception as e:
        print(f"User lookup error: {e}")
        return web.json_response({'error': str(e)}, status=500)

async def handle_get_filtered_users(request):
    self = request.app['bot']
    """Get users filtered by roles - for form applicant selection
    Default: users with NO roles (always included)
    Optional: additional roles to include (comma-separated via 'additional_roles' param)
    """
    try:
        additional_roles_param = request.query.get('additional_roles', '')
        additional_roles = [r.strip() for r in additional_roles_param.split(',') if r.strip()]
        
        users_data = []
        seen_ids = set()  # Avoid duplicates across guilds
        
        for guild in self.guilds:
            for member in guild.members:
                # Skip bots
                if member.bot:
                    continue
                
                # Skip already seen users (in case of multiple guilds)
                if member.id in seen_ids:
                    continue
                
                # Get member roles (exclude @everyone)
                member_roles = [r.name for r in member.roles if not r.is_default()]
                
                # Default: users with NO roles are always included
                has_no_roles = len(member_roles) == 0
                
                # Additional: users with any of the specified additional roles
                has_additional_role = any(r in additional_roles for r in member_roles) if additional_roles else False
                
                if has_no_roles or has_additional_role:
                    seen_ids.add(member.id)
                    users_data.append({
                        'id': str(member.id),
                        'username': member.name,
                        'display_name': member.display_name,
                        'global_name': member.global_name if hasattr(member, 'global_name') else None,
                        'avatar': str(member.display_avatar.url) if member.display_avatar else None
                    })
        
        # Sort by display name
        users_data.sort(key=lambda x: x['display_name'].lower())
        
        return web.json_response({
            'success': True, 
            'users': users_data,
            'count': len(users_data)
        })
    except Exception as e:
        print(f"Filtered users error: {e}")
        return web.json_response({'error': str(e)}, status=500)

# --- SERVER WELCOME SETTINGS HANDLERS ---

async def handle_get_server_welcome(request):
    self = request.app['bot']
    """Get server welcome settings from Redis"""
    try:
        data = {
            'enabled': r.get('server_welcome_enabled') == 'true',
            'channel_id': r.get('server_welcome_channel_id') or '',
            'message_content': r.get('server_welcome_message') or '',
            'banner_image': r.get('server_welcome_external_image') or '',
            'use_embed': r.get('server_welcome_use_embed') == 'true',
            'embed_data': {
                'title': r.get('server_welcome_embed_title') or '',
                'description': r.get('server_welcome_embed_description') or '',
                'image': r.get('server_welcome_image_url') or '',
                'color': r.get('server_welcome_embed_color') or '#5865f2'
            }
        }
        return web.json_response({'success': True, 'data': data})
    except Exception as e:
        print(f"Get server welcome error: {e}")
        return web.json_response({'error': str(e)}, status=500)

async def handle_set_server_welcome(request):
    self = request.app['bot']
    """Save server welcome settings to Redis"""
    try:
        data = await request.json()
        
        # Save to Redis
        r.set('server_welcome_enabled', 'true' if data.get('enabled') else 'false')
        r.set('server_welcome_channel_id', data.get('channel_id', ''))
        r.set('server_welcome_message', data.get('message_content', ''))
        r.set('server_welcome_external_image', data.get('banner_image', ''))
        r.set('server_welcome_use_embed', 'true' if data.get('use_embed') else 'false')
        
        # Embed data
        embed_data = data.get('embed_data', {})
        r.set('server_welcome_embed_title', embed_data.get('title', ''))
        r.set('server_welcome_embed_description', embed_data.get('description', ''))
        r.set('server_welcome_image_url', embed_data.get('image', ''))
        r.set('server_welcome_embed_color', embed_data.get('color', '#5865f2'))
        
        print(f"✅ Server welcome settings saved")
        return web.json_response({'success': True})
    except Exception as e:
        print(f"Set server welcome error: {e}")
        return web.json_response({'error': str(e)}, status=500)

async def handle_get_server_leave(request):
    self = request.app['bot']
    """Get server leave settings from Redis"""
    try:
        data = {
            'enabled': r.get('server_leave_enabled') == 'true',
            'channel_id': r.get('server_leave_channel_id') or '',
            'message_content': r.get('server_leave_message') or '',
            'use_embed': r.get('server_leave_use_embed') == 'true',
            'embed_data': {
                'title': r.get('server_leave_embed_title') or '',
                'description': r.get('server_leave_embed_description') or '',
                'color': r.get('server_leave_embed_color') or '#f04747'
            }
        }
        return web.json_response({'success': True, 'data': data})
    except Exception as e:
        print(f"Get server leave error: {e}")
        return web.json_response({'error': str(e)}, status=500)

async def handle_set_server_leave(request):
    self = request.app['bot']
    """Save server leave settings to Redis"""
    try:
        data = await request.json()
        
        r.set('server_leave_enabled', 'true' if data.get('enabled') else 'false')
        r.set('server_leave_channel_id', data.get('channel_id', ''))
        r.set('server_leave_message', data.get('message_content', ''))
        r.set('server_leave_use_embed', 'true' if data.get('use_embed') else 'false')
        
        embed_data = data.get('embed_data', {})
        r.set('server_leave_embed_title', embed_data.get('title', ''))
        r.set('server_leave_embed_description', embed_data.get('description', ''))
        r.set('server_leave_embed_color', embed_data.get('color', '#f04747'))
        
        print(f"✅ Server leave settings saved")
        return web.json_response({'success': True})
    except Exception as e:
        print(f"Set server leave error: {e}")
        return web.json_response({'error': str(e)}, status=500)

async def handle_get_voice_logs(request):
    self = request.app['bot']
    """Get voice log settings from Redis"""
    try:
        data = {
            'enabled': r.get('voice_logs_enabled') == 'true',
            'channel_id': r.get('voice_logs_channel_id') or '',
        }
        return web.json_response({'success': True, 'data': data})
    except Exception as e:
        print(f"Get voice logs error: {e}")
        return web.json_response({'error': str(e)}, status=500)

async def handle_set_voice_logs(request):
    self = request.app['bot']
    """Save voice log settings to Redis"""
    try:
        data = await request.json()
        r.set('voice_logs_enabled', 'true' if data.get('enabled') else 'false')
        r.set('voice_logs_channel_id', data.get('channel_id', ''))
        
        print(f"✅ Voice logs settings saved")
        return web.json_response({'success': True})
    except Exception as e:
        print(f"Set voice logs error: {e}")
        return web.json_response({'error': str(e)}, status=500)

async def _download_banner_image(image_url: str):
    """Download banner image from URL with internal Docker fallback.
    Returns discord.File or None if download fails.
    """
    urls_to_try = [image_url]
    
    # If URL is external, add internal Docker fallback (http://web/assets/uploads/...)
    if '/assets/uploads/' in image_url:
        path = '/assets/uploads/' + image_url.split('/assets/uploads/')[-1]
        internal_url = f'http://web{path}'
        if internal_url != image_url:
            urls_to_try.append(internal_url)
    
    for url in urls_to_try:
        try:
            async with aiohttp.ClientSession() as session:
                async with session.get(url, timeout=aiohttp.ClientTimeout(total=30)) as resp:
                    if resp.status == 200:
                        image_data = await resp.read()
                        filename = image_url.split('/')[-1].split('?')[0]
                        if not filename or '.' not in filename:
                            filename = 'banner.png'
                        print(f"✅ Downloaded banner image: {filename} ({len(image_data)} bytes) from {url}")
                        return discord.File(io.BytesIO(image_data), filename=filename)
                    else:
                        print(f"⚠️ Banner download got status {resp.status} from {url}")
        except Exception as e:
            print(f"⚠️ Banner download failed from {url}: {e}")
    
    print(f"❌ All banner download attempts failed for: {image_url}")
    return None

async def handle_test_server_welcome(request):
    self = request.app['bot']
    """Send a test server welcome message"""
    try:
        data = await request.json()
        
        channel_id = data.get('channel_id')
        if not channel_id:
            return web.json_response({'error': 'Missing channel_id'}, status=400)
        
        channel = self.get_channel(int(channel_id))
        if not channel:
            return web.json_response({'error': 'Channel not found'}, status=404)
        
        message_content = data.get('message_content', '')
        # Replace placeholders with test values
        message_content = message_content.replace('{user}', '@TestUser')
        message_content = message_content.replace('{server}', channel.guild.name)
        message_content = message_content.replace('{count}', str(channel.guild.member_count))
        
        # Download banner image as file attachment
        banner_image = data.get('banner_image', '')
        file_attachment = None
        
        if banner_image:
            file_attachment = await _download_banner_image(banner_image)
            if not file_attachment:
                # Fallback: add URL to message
                if message_content:
                    message_content += f"\n{banner_image}"
                else:
                    message_content = banner_image
        
        use_embed = data.get('use_embed', False)
        
        if use_embed:
            embed_data = data.get('embed_data', {})
            title = (embed_data.get('title', '') or 'Welcome!').replace('{user}', 'TestUser')
            description = (embed_data.get('description', '') or '').replace('{user}', '@TestUser')
            color_hex = embed_data.get('color', '#5865f2')
            image_url = embed_data.get('image', '')
            
            try:
                color = discord.Color.from_str(color_hex)
            except:
                color = discord.Color.blue()
            
            embed = discord.Embed(
                title=title,
                description=description,
                color=color
            )
            
            if image_url:
                embed.set_image(url=image_url)
            
            embed.set_footer(text="🔔 This is a TEST welcome message")
            embed.timestamp = datetime.now()
            
            if file_attachment:
                await channel.send(file=file_attachment)
            if message_content:
                await channel.send(content=message_content, embed=embed)
            else:
                await channel.send(embed=embed)
        else:
            final_content = message_content if message_content else ""
            final_content += "\n\n*🔔 This is a TEST welcome message*"
            
            # Send file first, then message
            if file_attachment:
                await channel.send(file=file_attachment)
                await channel.send(content=final_content.strip())
            elif message_content:
                await channel.send(content=final_content.strip())
            else:
                await channel.send(content="*🔔 No message content configured - this is a test*")
        
        print(f"✅ Test welcome sent to {channel.name}")
        return web.json_response({'success': True})
    except Exception as e:
        print(f"Test server welcome error: {e}")
        return web.json_response({'error': str(e)}, status=500)

async def handle_steam_proxy(request):
    """Proxy for Steam API requests to bypass local network blocking."""
    try:
        url = request.query.get('url')
        if not url:
            return web.json_response({'error': 'Missing url parameter'}, status=400)
            
        async with aiohttp.ClientSession() as session:
            if request.method == 'POST':
                # Forward POST data
                post_data = await request.read()
                headers = {'Content-Type': request.headers.get('Content-Type', 'application/x-www-form-urlencoded')}
                async with session.post(url, data=post_data, headers=headers) as response:
                    text = await response.text()
                    return web.Response(text=text, headers={'Content-Type': response.headers.get('Content-Type', 'text/plain')})
            else:
                # GET request
                async with session.get(url) as response:
                    if response.status == 200:
                        try:
                            data = await response.json()
                            return web.json_response(data)
                        except:
                            text = await response.text()
                            return web.Response(text=text, headers={'Content-Type': response.headers.get('Content-Type', 'application/json')})
                    else:
                        return web.json_response({'error': f'Steam API returned {response.status}'}, status=response.status)
    except Exception as e:
        print(f"Steam Proxy error: {e}", flush=True)
        return web.json_response({'error': str(e)}, status=500)

async def start_server(bot):
    try:
        app = web.Application(client_max_size=50 * 1024 * 1024, middlewares=[api_key_middleware])
        app['bot'] = bot
        
        app.router.add_get('/health', handle_health_check)
        app.router.add_post('/webhook', handle_legacy_webhook)
        app.router.add_post('/embed/send', handle_embed_request)
        app.router.add_post('/embed/edit', handle_embed_edit)
        app.router.add_post('/event/create', handle_event_request)
        app.router.add_post('/event/cancel', handle_event_cancel)
        app.router.add_get('/events/active', handle_get_active_events)
        app.router.add_get('/event/rsvp', handle_get_rsvp)
        app.router.add_get('/channels', handle_get_channels)
        app.router.add_get('/roles', handle_get_roles)
        app.router.add_get('/members', handle_get_members)
        
        # Bot Management Routes
        app.router.add_get('/bot/status', handle_get_bot_status)
        app.router.add_post('/bot/settings', handle_update_bot_settings)
        
        # Voice Channel Routes
        app.router.add_post('/bot/join', handle_join_voice)
        app.router.add_post('/bot/leave', handle_leave_voice)
        app.router.add_get('/voice_channels', handle_get_voice_channels)
        
        # Welcome Sound Routes
        app.router.add_get('/settings/welcome-sound', handle_get_welcome_sound)
        app.router.add_post('/settings/welcome-sound', handle_set_welcome_sound)
        
        # Server Welcome Routes
        app.router.add_get('/settings/server-welcome', handle_get_server_welcome)
        app.router.add_post('/settings/server-welcome', handle_set_server_welcome)
        app.router.add_post('/settings/server-welcome/test', handle_test_server_welcome)
        app.router.add_get('/settings/server-leave', handle_get_server_leave)
        app.router.add_post('/settings/server-leave', handle_set_server_leave)
        app.router.add_get('/settings/voice-logs', handle_get_voice_logs)
        app.router.add_post('/settings/voice-logs', handle_set_voice_logs)
        
        # Permission Routes
        app.router.add_get('/settings/permissions', handle_get_permissions)
        app.router.add_post('/settings/permissions', handle_save_permissions)
        
        # Saved Messages Routes (Message Builder)
        app.router.add_get('/messages', handle_list_messages)
        app.router.add_get('/messages/{id}', handle_get_message)
        app.router.add_post('/messages', handle_save_message)
        app.router.add_put('/messages/{id}', handle_update_message)
        app.router.add_delete('/messages/{id}', handle_delete_message)
        
        # Discord Feed Routes
        app.router.add_get('/feed/settings', handle_get_feed_settings)
        app.router.add_post('/feed/settings', handle_save_feed_settings)
        app.router.add_get('/feed/messages', handle_get_feed_messages)
        
        # Role Assignment Route
        app.router.add_post('/roles/assign', handle_assign_role)
        
        # Ticket System Routes
        app.router.add_post('/tickets/panel/send', handle_ticket_panel_send)
        app.router.add_post('/tickets/create', handle_ticket_create)
        app.router.add_post('/tickets/close', handle_ticket_close)
        app.router.add_get('/categories', handle_get_categories)
        
        # User Lookup Routes
        app.router.add_get('/users/lookup', handle_lookup_user)
        app.router.add_get('/users/filtered', handle_get_filtered_users)
        
        # Steam Proxy
        app.router.add_get('/proxy/steam', handle_steam_proxy)
        app.router.add_post('/proxy/steam', handle_steam_proxy)
        
        runner = web.AppRunner(app)
        await runner.setup()
        site = web.TCPSite(runner, '0.0.0.0', PORT)
        print(f"Starting Web Server on port {PORT}", flush=True)
        await site.start()
        bot.web_server_started = True
    except Exception as e:
        print(f"Failed to start web server: {e}", flush=True)
