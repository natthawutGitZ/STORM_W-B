import discord
import asyncio
from datetime import timezone, timedelta
BANGKOK_TZ = timezone(timedelta(hours=7))

from discord.ext import commands
import aiohttp
import json
import os
import io
import redis

REDIS_HOST = os.getenv('REDIS_HOST', 'redis')
r = redis.Redis(host=REDIS_HOST, port=6379, db=0, decode_responses=True)
from datetime import datetime
from ui.components import WelcomeView, RejectReasonModal, RescheduleModal

class EventsCog(commands.Cog):
    def __init__(self, bot: commands.Bot):
        self.bot = bot

    @commands.Cog.listener()
    async def on_interaction(self, interaction: discord.Interaction):
        try:
            if interaction.type == discord.InteractionType.component:
                custom_id = interaction.data.get('custom_id')

                # --- APP SELECT MENU ---
                if custom_id and custom_id.startswith('app_select:'):
                    response_id = custom_id.split(':')[1]
                    selected_val = interaction.data['values'][0]

                    if selected_val == 'approve':
                        async with aiohttp.ClientSession() as session:
                            await session.post('http://web/admin/form_actions.php', data={
                                'action': 'update_response_status',
                                'id': response_id,
                                'status': 'accepted',
                                'admin_discord_id': str(interaction.user.id),
                                'admin_discord_name': interaction.user.display_name
                            })
                        await interaction.response.send_message(f"✅ Application #{response_id} **Approved**.", ephemeral=True)
                    elif selected_val == 'reject':
                        await interaction.response.send_modal(RejectReasonModal(response_id))
                    elif selected_val == 'reschedule':
                        await interaction.response.send_modal(RescheduleModal(response_id))

                # --- TICKET OPEN BUTTON ---
                elif custom_id and custom_id.startswith('ticket_open:'):
                    panel_id = custom_id.split(':')[1]
                    await interaction.response.defer(ephemeral=True)

                    try:
                        async with aiohttp.ClientSession() as session:
                            async with session.get(f'http://web/admin/ticket_actions.php?action=get_panel&id={panel_id}') as resp:
                                panel_data = await resp.json()

                        if not panel_data.get('success'):
                            await interaction.followup.send('❌ Ticket panel not found.', ephemeral=True)
                            return

                        panel = panel_data['panel']
                        async with aiohttp.ClientSession() as session:
                            async with session.get(f'http://web/admin/ticket_actions.php?action=list_tickets&panel_id={panel_id}&status=open') as resp:
                                tickets_data = await resp.json()

                        user_open = sum(1 for t in tickets_data.get('tickets', []) if t.get('user_id') == str(interaction.user.id))
                        max_tickets = int(panel.get('max_tickets', 1))

                        if user_open >= max_tickets:
                            await interaction.followup.send(f'❌ You already have {user_open} open ticket(s). Maximum is {max_tickets}.', ephemeral=True)
                            return

                        guild = interaction.guild
                        member = interaction.user
                        category_id = panel.get('category_id')
                        support_role_id = panel.get('support_role_id')
                        welcome_msg = panel.get('welcome_message', '')

                        overwrites = {
                            guild.default_role: discord.PermissionOverwrite(view_channel=False),
                            member: discord.PermissionOverwrite(view_channel=True, send_messages=True, read_message_history=True, attach_files=True),
                            guild.me: discord.PermissionOverwrite(view_channel=True, send_messages=True, manage_channels=True, manage_messages=True),
                        }

                        if support_role_id:
                            support_role = guild.get_role(int(support_role_id))
                            if support_role:
                                overwrites[support_role] = discord.PermissionOverwrite(view_channel=True, send_messages=True, read_message_history=True)

                        category = guild.get_channel(int(category_id)) if category_id else None
                        channel_name = f'ticket-{member.display_name.lower().replace(" ", "-")[:20]}'

                        ticket_channel = await guild.create_text_channel(
                            name=channel_name,
                            category=category,
                            overwrites=overwrites,
                            reason=f'Ticket opened by {member.display_name}'
                        )

                        welcome_embed = discord.Embed(
                            title='🎫 Ticket Opened',
                            description=welcome_msg if welcome_msg else f'Welcome {member.mention}! A staff member will be with you shortly.\n\nPlease describe your issue below.',
                            color=0x43b581,
                            timestamp=datetime.now()
                        )
                        welcome_embed.set_footer(text=f'Ticket by {member.display_name} • Panel: {panel.get("title", "Support")}')

                        close_view = discord.ui.View(timeout=None)
                        close_btn = discord.ui.Button(label='Close Ticket', style=discord.ButtonStyle.red, emoji='🔒', custom_id=f'ticket_close:{ticket_channel.id}')
                        close_view.add_item(close_btn)

                        await ticket_channel.send(content=member.mention, embed=welcome_embed, view=close_view)

                        async with aiohttp.ClientSession() as session:
                            await session.post('http://web/admin/ticket_actions.php', data={
                                'action': 'save_ticket',
                                'panel_id': panel_id,
                                'user_id': str(interaction.user.id),
                                'user_name': interaction.user.display_name,
                                'channel_id': str(ticket_channel.id),
                                'channel_name': ticket_channel.name
                            })

                        await interaction.followup.send(f'✅ Ticket created! {ticket_channel.mention}', ephemeral=True)

                    except Exception as e:
                        print(f"[ERROR] ticket_open interaction: {e}")
                        await interaction.followup.send(f'❌ Error creating ticket: {str(e)}', ephemeral=True)

                # --- TICKET CLOSE BUTTON ---
                elif custom_id and custom_id.startswith('ticket_close:'):
                    target_channel_id = custom_id.split(':')[1]
                    await interaction.response.defer(ephemeral=True)
                    try:
                        channel = self.bot.get_channel(int(target_channel_id))
                        transcript_data = []
                        if channel:
                            from api.server import fetch_channel_transcript
                            transcript_data = await fetch_channel_transcript(channel)

                        ticket_id = None
                        async with aiohttp.ClientSession() as session:
                            async with session.post('http://web/admin/ticket_actions.php', data={
                                'action': 'close_ticket_by_channel',
                                'channel_id': target_channel_id,
                                'closed_by': interaction.user.display_name
                            }) as resp:
                                try:
                                    data = await resp.json()
                                    ticket_id = data.get('ticket_id')
                                except:
                                    pass
                            
                            if ticket_id and transcript_data:
                                await session.post('http://web/admin/ticket_actions.php', data={
                                    'action': 'save_transcript',
                                    'ticket_id': str(ticket_id),
                                    'messages': json.dumps(transcript_data, ensure_ascii=False),
                                    'message_count': str(len(transcript_data))
                                })
                                print(f"📜 Saved transcript for ticket #{ticket_id}", flush=True)

                        closing_embed = discord.Embed(
                            title='🔒 Ticket Closed',
                            description=f'This ticket has been closed by {interaction.user.mention}.\nTranscript saved. This channel will be deleted in 5 seconds.',
                            color=0xf04747,
                            timestamp=datetime.now()
                        )
                        await interaction.followup.send(embed=closing_embed)

                        import asyncio
                        await asyncio.sleep(5)
                        if channel:
                            await channel.delete(reason=f'Ticket closed by {interaction.user.display_name}')

                    except Exception as e:
                        print(f"[ERROR] ticket_close interaction: {e}")
                        await interaction.followup.send(f'❌ Error closing ticket: {str(e)}', ephemeral=True)

        except Exception as e:
            print(f"Interaction Error: {e}")

    @commands.Cog.listener()
    async def on_member_remove(self, member):
        """Called when a member leaves the server"""
        try:
            # Check if server leave is enabled
            enabled = r.get('server_leave_enabled') == 'true'
            if not enabled:
                return
            
            channel_id = r.get('server_leave_channel_id')
            if not channel_id:
                return
            
            channel = self.bot.get_channel(int(channel_id))
            if not channel:
                return
            
            # Process message content
            message_content = r.get('server_leave_message') or ''
            message_content = message_content.replace('{user}', member.name)
            message_content = message_content.replace('{server}', member.guild.name)
            message_content = message_content.replace('{count}', str(member.guild.member_count))
        
            use_embed = r.get('server_leave_use_embed') == 'true'
        
            if use_embed:
                title = (r.get('server_leave_embed_title') or 'User Left').replace('{user}', member.name)
                description = (r.get('server_leave_embed_description') or '').replace('{user}', member.name)
                color_hex = r.get('server_leave_embed_color') or '#f04747'
            
                try:
                    color = discord.Color.from_str(color_hex)
                except:
                    color = discord.Color.red()
                
                embed = discord.Embed(
                    title=title,
                    description=description,
                    color=color
                )
            
                embed.set_author(name=f"{member.name} left", icon_url=member.display_avatar.url if member.display_avatar else None)
                embed.timestamp = datetime.now()
            
                if message_content:
                    await channel.send(content=message_content, embed=embed)
                else:
                    await channel.send(embed=embed)
            else:
                if message_content:
                    await channel.send(content=message_content)
                
            print(f"✅ Sent server leave to {channel.name} for {member.name}")
        
        except Exception as e:
            print(f"❌ Error sending server leave message: {e}")

    @commands.Cog.listener()
    async def on_member_join(self, member):
        """Called when a new member joins the server"""
        print(f"👋 Member Joined: {member.name} (ID: {member.id})")
    
        try:
            # Check if enabled
            enabled = r.get('server_welcome_enabled') == 'true'
            if not enabled:
                return

            channel_id = r.get('server_welcome_channel_id')
            if not channel_id:
                return

            channel = self.bot.get_channel(int(channel_id))
            if not channel:
                print(f"⚠️ Welcome Channel ID {channel_id} not found")
                return

            message_content = r.get('server_welcome_message') or ''
            # Simple placeholder replacement
            message_content = message_content.replace('{user}', member.mention)
            message_content = message_content.replace('{server}', member.guild.name)
            message_content = message_content.replace('{count}', str(member.guild.member_count))

            # Download banner image as file attachment
            external_image = r.get('server_welcome_external_image')
            file_attachment = None
        
            if external_image:
                try:
                    async with aiohttp.ClientSession() as session:
                        async with session.get(external_image) as resp:
                            if resp.status == 200:
                                image_data = await resp.read()
                                # Get filename from URL or use default
                                filename = external_image.split('/')[-1].split('?')[0]
                                if not filename or '.' not in filename:
                                    filename = 'banner.png'
                                file_attachment = discord.File(io.BytesIO(image_data), filename=filename)
                                print(f"✅ Downloaded banner image: {filename}")
                except Exception as img_err:
                    print(f"Failed to download banner image: {img_err}")
                    # Fallback: add URL to message if download fails
                    if message_content:
                        message_content += f"\n{external_image}"
                    else:
                        message_content = external_image
        
            use_embed = r.get('server_welcome_use_embed') == 'true'

            if use_embed:
                title = (r.get('server_welcome_embed_title') or 'Welcome!').replace('{user}', member.name)
                description = (r.get('server_welcome_embed_description') or '').replace('{user}', member.mention)
                color_hex = r.get('server_welcome_embed_color') or '#5865f2'
                image_url = r.get('server_welcome_image_url')

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
            
                embed.set_thumbnail(url=member.display_avatar.url)
                embed.set_footer(text=f"User ID: {member.id}")
                embed.timestamp = datetime.now()

                # Send: file first (banner), then message content after
                if file_attachment:
                    await channel.send(file=file_attachment)
                    if message_content:
                        await channel.send(content=message_content, embed=embed)
                    else:
                        await channel.send(embed=embed)
                else:
                    await channel.send(content=message_content, embed=embed)
                print(f"✅ Sent server welcome to {channel.name} for {member.name}")
            else:
                # Non-embed mode: send banner first, then message
                if file_attachment:
                    await channel.send(file=file_attachment)
                    if message_content:
                        await channel.send(content=message_content)
                elif message_content:
                    await channel.send(content=message_content)
                print(f"✅ Sent server welcome (text) to {channel.name} for {member.name}")

        except Exception as e:
            print(f"❌ Error sending server welcome message: {e}")


    @commands.Cog.listener()
    async def on_message(self, message):
        """Discord Feed System: Capture messages from monitored channels"""
        # Skip bot messages
        if message.author.bot:
            return
    
        # Skip DMs
        if not message.guild:
            return
    
        try:
            # Get monitored channels from Redis
            monitored_channels = self.get_feed_monitored_channels()
            channel_id = str(message.channel.id)
        
            if channel_id not in monitored_channels:
                return
        
            # Store message to database
            await self.store_feed_message(message)
            print(f"📥 Feed: Stored message from #{message.channel.name} by {message.author.name}")
        
        except Exception as e:
            print(f"❌ Feed on_message error: {e}")

    def get_feed_monitored_channels(self):
        """Get list of channel IDs being monitored for feed"""
        try:
            # Try to get from Redis cache first
            cached = r.get('feed_monitored_channels')
            if cached:
                return json.loads(cached)
        
            # Fallback: return empty (settings not configured yet)
            return []
        except:
            return []

    async def store_feed_message(self, message: discord.Message):
        """Store a Discord message to the feed database"""
        # Extract attachments
        attachments = []
        for att in message.attachments:
            attachments.append({
                'url': att.url,
                'filename': att.filename,
                'content_type': att.content_type,
                'size': att.size
            })
    
        # Determine message type
        has_images = any(att.get('content_type', '').startswith('image/') for att in attachments)
        has_files = len(attachments) > 0 and not has_images
    
        if has_images and message.content:
            msg_type = 'mixed'
        elif has_images:
            msg_type = 'image'
        elif has_files:
            msg_type = 'file'
        else:
            msg_type = 'text'
    
        # Extract embeds (for link previews)
        embeds_data = []
        for embed in message.embeds:
            if embed.type == 'rich' or embed.type == 'link':
                embeds_data.append({
                    'title': embed.title,
                    'description': embed.description,
                    'url': embed.url,
                    'thumbnail': str(embed.thumbnail.url) if embed.thumbnail else None,
                    'image': str(embed.image.url) if embed.image else None
                })
    
        # Prepare data for API
        data = {
            'channel_id': str(message.channel.id),
            'message_id': str(message.id),
            'author_id': str(message.author.id),
            'author_name': message.author.display_name,
            'author_avatar': str(message.author.display_avatar.url),
            'content': message.content,
            'attachments': attachments,
            'embeds': embeds_data,
            'message_type': msg_type,
            'discord_created_at': message.created_at.isoformat()
        }
    
        # Store via internal API (will be handled by PHP side)
        # For now, store directly to Redis for the PHP API to pick up
        r.lpush('feed_messages_queue', json.dumps(data))
        r.ltrim('feed_messages_queue', 0, 999)  # Keep last 1000 messages in queue

    async def play_welcome_sound_in_channel(self, voice_client):
        """Play welcome sound in the current voice channel (bot must already be connected)"""
        try:
            sound_path = '/app/sounds/welcome.mp3'
            
            if not os.path.exists(sound_path):
                print("⚠️ Welcome sound file not found")
                return
            
            # Check if already playing
            if voice_client.is_playing():
                print("⚠️ Already playing audio, skipping")
                return
            
            # Create FFmpeg audio source from local file
            source = discord.FFmpegPCMAudio(sound_path)
            
            # Play the audio
            voice_client.play(source, after=lambda e: print(f'Player error: {e}') if e else None)
            print("🔊 Playing welcome sound...")
            
            # Wait for audio to finish (non-blocking)
            while voice_client.is_playing():
                await asyncio.sleep(0.5)
            
            print("✅ Welcome sound finished")
            # Note: We do NOT disconnect after playing - bot stays in channel
            
        except Exception as e:
            print(f"Error playing welcome sound: {e}")
    
    @commands.Cog.listener()
    async def on_voice_state_update(self, member, before, after):
        """Called when a member changes voice state (join/leave/mute/etc.)"""
        # Ignore bot's own changes
        if member.bot:
            return
            
        # --- Voice Logs System ---
        try:
            logs_enabled = r.get('voice_logs_enabled') == 'true'
            log_channel_id = r.get('voice_logs_channel_id')
            
            if logs_enabled and log_channel_id:
                log_channel = self.bot.get_channel(int(log_channel_id))
                if log_channel:
                    # Joined a channel
                    if before.channel is None and after.channel is not None:
                        embed = discord.Embed(
                            description=f"⬇️ {member.mention} joined voice channel 🔊 | **{after.channel.name}**",
                            color=discord.Color.from_str('#43b581') # Green
                        )
                        embed.set_author(name=member.name, icon_url=member.display_avatar.url if member.display_avatar else None)
                        embed.add_field(name="IDs", value=f"```ini\nUser = {member.id}\nVoice Channel = {after.channel.id}\n```", inline=False)
                        embed.set_footer(text=f"{self.bot.user.name} • Today at {datetime.now(BANGKOK_TZ).strftime('%H:%M')}", icon_url=self.bot.user.display_avatar.url if self.bot.user.display_avatar else None)
                        await log_channel.send(embed=embed)
                        
                    # Left a channel
                    elif before.channel is not None and after.channel is None:
                        embed = discord.Embed(
                            description=f"⬆️ {member.mention} left voice channel 🔊 | **{before.channel.name}**",
                            color=discord.Color.from_str('#f04747') # Red
                        )
                        embed.set_author(name=member.name, icon_url=member.display_avatar.url if member.display_avatar else None)
                        embed.add_field(name="IDs", value=f"```ini\nUser = {member.id}\nVoice Channel = {before.channel.id}\n```", inline=False)
                        embed.set_footer(text=f"{self.bot.user.name} • Today at {datetime.now(BANGKOK_TZ).strftime('%H:%M')}", icon_url=self.bot.user.display_avatar.url if self.bot.user.display_avatar else None)
                        await log_channel.send(embed=embed)
                        
                    # Switched channels
                    elif before.channel is not None and after.channel is not None and before.channel.id != after.channel.id:
                        embed = discord.Embed(
                            description=f"➡️ {member.mention} switched voice channels\nFrom 🔊 | **{before.channel.name}** to 🔊 | **{after.channel.name}**",
                            color=discord.Color.from_str('#faa61a') # Yellow/Orange
                        )
                        embed.set_author(name=member.name, icon_url=member.display_avatar.url if member.display_avatar else None)
                        embed.add_field(name="IDs", value=f"```ini\nUser = {member.id}\nOld = {before.channel.id}\nNew = {after.channel.id}\n```", inline=False)
                        embed.set_footer(text=f"{self.bot.user.name} • Today at {datetime.now(BANGKOK_TZ).strftime('%H:%M')}", icon_url=self.bot.user.display_avatar.url if self.bot.user.display_avatar else None)
                        await log_channel.send(embed=embed)
        except Exception as e:
            print(f"❌ Error sending voice log: {e}")
            
        # Check if user joined a voice channel (was not in one before, now is)
        if before.channel is None and after.channel is not None:
            try:
                # Check if welcome system is enabled
                enabled = r.get('welcome_sound_enabled') == 'true'
                if not enabled:
                    return
                
                # Check target channel filter
                target_channel_id = r.get('welcome_sound_channel')
                if target_channel_id and str(after.channel.id) != target_channel_id:
                    return
                
                print(f"🔊 Welcome triggered for {member.display_name} joining {after.channel.name}")
                
                # Check if sound file exists
                sound_path = '/app/sounds/welcome.mp3'
                has_sound = os.path.exists(sound_path)
                
                # --- Play Welcome Sound ---
                if has_sound:
                    voice_client = None
                    was_already_connected = False
                    
                    try:
                        # Check if bot is already in a voice channel in this guild
                        existing_vc = after.channel.guild.voice_client
                        if existing_vc and existing_vc.is_connected():
                            was_already_connected = True
                            if existing_vc.channel.id != after.channel.id:
                                await existing_vc.move_to(after.channel)
                            voice_client = existing_vc
                        else:
                            # Auto-join the channel
                            voice_client = await after.channel.connect()
                        
                        # Wait before playing (configurable delay)
                        delay = int(r.get('welcome_sound_delay') or 2)
                        if delay > 0:
                            await asyncio.sleep(delay)
                        
                        # Play the welcome sound
                        await self.play_welcome_sound_in_channel(voice_client)
                        
                    except Exception as e:
                        print(f"❌ Error playing welcome sound: {e}")
                    finally:
                        # Disconnect after playing (only if we auto-joined)
                        if not was_already_connected and voice_client and voice_client.is_connected():
                            try:
                                await voice_client.disconnect()
                                print(f"📴 Disconnected from {after.channel.name} after welcome sound")
                            except Exception as e:
                                print(f"⚠️ Error disconnecting: {e}")
                
                # --- Send Welcome DM ---
                message_text = r.get('welcome_message_text')
                dropdown_json = r.get('welcome_dropdown_options')
                
                if message_text or dropdown_json:
                    try:
                        options_data = json.loads(dropdown_json) if dropdown_json else []
                    except:
                        options_data = []
    
                    try:
                        link_buttons = json.loads(r.get('welcome_link_buttons') or '[]')
                    except:
                        link_buttons = []
                        
                    view = WelcomeView(options_data, link_buttons) if (options_data or link_buttons) else None
                    final_text = message_text.replace('{user}', member.mention) if message_text else f"Welcome {member.mention}!"
                    
                    try:
                        await member.send(content=final_text, view=view)
                        print(f"📨 Sent welcome DM to {member.name}")
                    except discord.Forbidden:
                        print(f"❌ Failed to DM {member.name}: DMs disabled")
                    except Exception as e:
                        print(f"❌ Error sending welcome DM: {e}")
                
            except Exception as e:
                print(f"Welcome sound error: {e}")

async def setup(bot: commands.Bot):
    await bot.add_cog(EventsCog(bot))
