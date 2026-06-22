import discord
from discord import app_commands
import os
import asyncio
import base64
import io
import time
from datetime import datetime
from dotenv import load_dotenv
from aiohttp import web
import aiohttp
import json
import re
import redis

# Import Services
from services.embeds import EmbedService
from services.events import EventService, RSVPView
from services.database import DatabaseService
from services.permissions import PermissionService
from discord.ext import tasks
# import dateutil.parser # Avoid external dependency if possible

load_dotenv()

TOKEN = os.getenv('DISCORD_BOT_TOKEN')
PORT = 5000

# Redis Setup
REDIS_HOST = os.getenv('REDIS_HOST', 'redis')
r = redis.Redis(host=REDIS_HOST, port=6379, db=0, decode_responses=True)

# API Key Authentication
BOT_API_KEY = os.getenv('BOT_API_KEY', '')

def check_auth(command_name):
    async def predicate(interaction: discord.Interaction):
        # Admin always has permission
        if interaction.user.guild_permissions.administrator:
            return True
            
        # Check permissions via service
        has_perm = PermissionService.check_permission(command_name, interaction.user.roles)
        
        if has_perm:
            return True
            
        await interaction.response.send_message("❌ You do not have permission to use this command.", ephemeral=True)
        return False
    return app_commands.check(predicate)

@web.middleware
async def api_key_middleware(request, handler):
    """Middleware to check API key for all requests"""
    # Skip auth for health check endpoints if needed
    if request.path == '/health':
        return await handler(request)
    
    # DISABLED: API key check not needed for internal Docker network
    # Check API key if configured
    # if BOT_API_KEY:
    #     provided_key = request.headers.get('X-API-Key', '')
    #     if provided_key != BOT_API_KEY:
    #         return web.json_response(
    #             {'error': 'Unauthorized', 'message': 'Invalid or missing API key'},
    #             status=401
    #         )
    
    return await handler(request)

# --- MODALS ---

class EventModal(discord.ui.Modal, title='Create New Event'):
    event_title = discord.ui.TextInput(label='Event Title', placeholder='e.g. Boss Fight', required=True)
    description = discord.ui.TextInput(label='Description', style=discord.TextStyle.paragraph, required=True)
    date_str = discord.ui.TextInput(label='Date (YYYY-MM-DD)', placeholder='2024-12-31', required=True)
    time_str = discord.ui.TextInput(label='Time (HH:MM)', placeholder='20:00', required=True)
    image_url = discord.ui.TextInput(label='Image URL (Optional)', required=False)

    async def on_submit(self, interaction: discord.Interaction):
        try:
            # Construct data payload compatible with EventService
            # We assume ISO format or simple string. Service expects separated date/time usually.
            data = {
                'title': self.event_title.value,
                'description': self.description.value,
                'date': self.date_str.value,
                'time': self.time_str.value,
                'image': self.image_url.value if self.image_url.value else None,
                'color': '#faa61a', # Default Orange
                'visibility': {'channel_id': str(interaction.channel_id)}
            }
            
            # Use Service to create Embed
            embed = EventService.create_event_embed(data)
            
            # Generate Event ID
            event_id = f"evt_{int(time.time())}"
            view = RSVPView(event_id)
            
            # Send (Ephemeral confirmation + Real Message)
            await interaction.response.send_message("Creating event...", ephemeral=True)
            
            # Send actual event message to channel
            msg = await interaction.channel.send(embed=embed, view=view)
            
            # Save to DB
            DatabaseService.create_event({
                'event_id': event_id,
                'title': data['title'],
                'description': data['description'],
                'date': data['date'],
                'time': data['time'],
                'timestamp': embed.timestamp.isoformat() if embed.timestamp else None,
                'image_url': data['image'],
                'color': data['color'],
                'author': interaction.user.display_name,
                'author_icon': str(interaction.user.display_avatar.url),
                'channel_id': str(interaction.channel_id),
                'message_id': str(msg.id),
                'guild_id': str(interaction.guild_id)
            })
            
            # Thread creation
            try:
                thread = await msg.create_thread(name=data['title'][:100])
                DatabaseService.update_event_thread_id(event_id, thread.id)
            except:
                pass
                
        except Exception as e:
            await interaction.followup.send(f"Error creating event: {str(e)}", ephemeral=True)

class ChannelSelectView(discord.ui.View):
    def __init__(self, content=None, embed=None):
        super().__init__(timeout=60)
        self.content = content
        self.embed = embed
    
    @discord.ui.select(cls=discord.ui.ChannelSelect, channel_types=[discord.ChannelType.text, discord.ChannelType.news], placeholder="Select a channel to send to...")
    async def select_channel(self, interaction: discord.Interaction, select: discord.ui.ChannelSelect):
        selected = select.values[0]
        
        try:
            # Resolve to full channel object
            channel = interaction.guild.get_channel(selected.id)
            if not channel:
                channel = await interaction.guild.fetch_channel(selected.id)

            # Send to selected channel
            await channel.send(content=self.content, embed=self.embed)
            
            await interaction.response.edit_message(content=f"✅ Sent to {channel.mention}", view=None, embed=None)
        except Exception as e:
            await interaction.response.edit_message(content=f"❌ Failed to send: {str(e)}", view=None)

class EmbedModal(discord.ui.Modal, title='Create Embed Message'):
    mention = discord.ui.TextInput(
        label='Mention (@role/@user)', 
        placeholder='@everyone, @RoleName, @Username',
        required=False
    )
    embed_title = discord.ui.TextInput(label='Title', required=True)
    description = discord.ui.TextInput(label='Description', style=discord.TextStyle.paragraph, required=True)
    color_hex = discord.ui.TextInput(label='Color Only Hex (#RRGGBB)', placeholder='#00b0f4', required=False)
    image_url = discord.ui.TextInput(label='Image URL (Optional)', required=False)
    
    async def on_submit(self, interaction: discord.Interaction):
        try:
            color = discord.Color.blue()
            if self.color_hex.value:
                try:
                    color = discord.Color.from_str(self.color_hex.value)
                except:
                    pass
            
            embed = discord.Embed(
                title=self.embed_title.value,
                description=self.description.value,
                color=color,
                timestamp=datetime.now()
            )
            
            if self.image_url.value:
                embed.set_image(url=self.image_url.value)
            
            # embed.set_footer(text=f"Sent by {interaction.user.display_name}", icon_url=interaction.user.display_avatar.url)
            
            # Process mentions
            mention_content = await process_mentions(self.mention.value, interaction.guild) if self.mention.value else None
            
            # Show channel selector instead of sending immediately
            view = ChannelSelectView(content=mention_content, embed=embed)
            await interaction.response.send_message("Please select a channel to send this message to:", view=view, ephemeral=True)
        except Exception as e:
            await interaction.response.send_message(f"Error sending embed: {str(e)}", ephemeral=True)

class SendMessageModal(discord.ui.Modal, title='Send Message'):
    """Modal for sending simple message without embed"""
    content = discord.ui.TextInput(
        label='Message', 
        style=discord.TextStyle.paragraph,
        required=True
    )
    
    def __init__(self, channel):
        super().__init__()
        self.channel = channel
        
    async def on_submit(self, interaction: discord.Interaction):
        await self.channel.send(self.content.value)
        await interaction.response.send_message("Message sent!", ephemeral=True)

class RescheduleModal(discord.ui.Modal, title='Reschedule Interview'):
    new_date = discord.ui.TextInput(label='New Date (YYYY-MM-DD)', placeholder='2024-12-31', required=True)
    new_time = discord.ui.TextInput(label='New Time (HH:MM)', placeholder='13:00', required=True)
    reason = discord.ui.TextInput(label='Reason (Optional)', style=discord.TextStyle.paragraph, required=False)

    def __init__(self, response_id):
        super().__init__()
        self.response_id = response_id

    async def on_submit(self, interaction: discord.Interaction):
        try:
             async with aiohttp.ClientSession() as session:
                payload = {
                    'response_id': self.response_id,
                    'date': self.new_date.value,
                    'time': self.new_time.value,
                    'reason': self.reason.value
                }
                async with session.post('http://web/api/internal_interviews.php?action=reschedule', json=payload) as resp:
                    result = await resp.json()
                    if result.get('success'):
                        await interaction.response.send_message(f"✅ Interview rescheduled to **{self.new_date.value} {self.new_time.value}**.", ephemeral=True)
                    else:
                        await interaction.response.send_message(f"❌ Failed: {result.get('error')}", ephemeral=True)
        except Exception as e:
            await interaction.response.send_message(f"Error: {str(e)}", ephemeral=True)

class RejectReasonModal(discord.ui.Modal, title='Reject Application'):
    reason = discord.ui.TextInput(label='Reason for Rejection', style=discord.TextStyle.paragraph, placeholder='Type your reason here...', required=True)

    def __init__(self, response_id):
        super().__init__()
        self.response_id = response_id

    async def on_submit(self, interaction: discord.Interaction):
        try:
             async with aiohttp.ClientSession() as session:
                await session.post('http://web/admin/form_actions.php', data={
                    'action': 'update_response_status',
                    'id': self.response_id,
                    'status': 'rejected',
                    'reason': self.reason.value,
                    'admin_discord_id': str(interaction.user.id),
                    'admin_discord_name': interaction.user.display_name
                })
             await interaction.response.send_message(f"❌ Application #{self.response_id} **Rejected**.", ephemeral=True)
        except Exception as e:
            await interaction.response.send_message(f"Error: {str(e)}", ephemeral=True)

async def replace_mentions_in_text(text: str, guild: discord.Guild) -> str:
    """Replace @Role and @User patterns with actual mentions"""
    if not text or not guild:
        return text
    
    # Handle @everyone and @here specifically if they aren't automatically handled by Discord in some contexts (text input usually sends them as text, but we want them to function)
    # Actually Discord handles @everyone/@here if sent as text content usually, but let's be safe or just leave them.
    # The main issue is Role/User names not being IDs.
    
    # 1. Replace Role patterns
    # Sort by length desc to avoid partial matches
    roles = sorted(guild.roles, key=lambda r: len(r.name), reverse=True)
    for role in roles:
        pattern = f"@{role.name}"
        if pattern in text:
             text = text.replace(pattern, role.mention)
    
    # 2. Replace Member patterns
    # This might be heavy for large servers, but acceptable for this use case
    members = sorted(guild.members, key=lambda m: len(m.display_name), reverse=True)
    for member in members:
        # Check display name first
        if f"@{member.display_name}" in text:
            text = text.replace(f"@{member.display_name}", member.mention)
        # Check username
        elif f"@{member.name}" in text:
            text = text.replace(f"@{member.name}", member.mention)
            
    return text

class VoiceJoinSelectView(discord.ui.View):
    def __init__(self):
        super().__init__(timeout=60)
    
    @discord.ui.select(cls=discord.ui.ChannelSelect, channel_types=[discord.ChannelType.voice, discord.ChannelType.stage_voice], placeholder="Select a voice channel to join...")
    async def select_voice(self, interaction: discord.Interaction, select: discord.ui.ChannelSelect):
        try:
            # select.values[0] might be an AppCommandChannel (partial) which doesn't have connect()
            selected_channel = select.values[0]
            
            # Resolve to full channel object
            channel = interaction.guild.get_channel(selected_channel.id)
            if not channel:
                channel = await interaction.guild.fetch_channel(selected_channel.id)

            if interaction.guild.voice_client:
                if interaction.guild.voice_client.channel.id != channel.id:
                    await interaction.guild.voice_client.move_to(channel)
                    await interaction.response.edit_message(content=f"✅ Moved to {channel.mention}", view=None)
                else:
                    await interaction.response.edit_message(content=f"ℹ️ Already in {channel.mention}", view=None)
            else:
                await channel.connect()
                await interaction.response.edit_message(content=f"✅ Joined {channel.mention}", view=None)
        except Exception as e:
            await interaction.response.edit_message(content=f"❌ Failed to join: {str(e)}", view=None)

class WelcomeView(discord.ui.View):
    def __init__(self, options_data=None, link_buttons=None):
        super().__init__(timeout=None) # Persistent view? Or timeout? Welcome messages might not need persistence if interaction is immediate. Let's say 300s.
        
        # options_data is a list of dicts: [{'label': 'Info', 'response_text': 'Here is info...'}]
        self.options_data = options_data if options_data else []
        
        # Create Select Menu
        options = []
        for i, opt in enumerate(self.options_data):
            label = opt.get('label', f'Option {i+1}')
            options.append(discord.SelectOption(label=label, value=str(i)))
            
        if options:
            select = discord.ui.Select(placeholder="Select an option...", options=options)
            select.callback = self.select_callback
            self.add_item(select)

        # Add Link Buttons
        if link_buttons:
            for btn in link_buttons:
                if btn.get('label') and btn.get('url'):
                     # Handle emoji if present (simple check, might need robust parsing if users input complex emojis)
                     # For now, just Label and URL as per plan
                     self.add_item(discord.ui.Button(label=btn['label'], url=btn['url']))

    async def select_callback(self, interaction: discord.Interaction):
        try:
            # Get selected index
            index = int(interaction.data['values'][0])
            
            # Get response text
            if 0 <= index < len(self.options_data):
                response_text = self.options_data[index].get('response_text', 'No response configured.')
                await interaction.response.send_message(response_text, ephemeral=True)
            else:
                await interaction.response.send_message("❌ Invalid selection.", ephemeral=True)
        except Exception as e:
             await interaction.response.send_message(f"❌ Error: {str(e)}", ephemeral=True)

class HelpView(discord.ui.View):
    def __init__(self):
        super().__init__(timeout=None) # Persistent view

    @discord.ui.button(label="📅 Create Event", style=discord.ButtonStyle.primary, custom_id="help_create_event")
    async def create_event(self, interaction: discord.Interaction, button: discord.ui.Button):
        # Check permission (optional, but good practice if command is restricted)
        # Using the check_auth logic might be complex here since it's a decorator, 
        # but we can try-catch or just let the modal open.
        # For better UX, we just open the modal.
        await interaction.response.send_modal(EventModal())

    @discord.ui.button(label="📝 Send Embed", style=discord.ButtonStyle.success, custom_id="help_send_embed")
    async def send_embed(self, interaction: discord.Interaction, button: discord.ui.Button):
        await interaction.response.send_modal(EmbedModal())

    @discord.ui.button(label="📨 Send Message", style=discord.ButtonStyle.secondary, custom_id="help_send_message")
    async def send_message(self, interaction: discord.Interaction, button: discord.ui.Button):
        await interaction.response.send_modal(SendMessageModal(interaction.channel))
    
    @discord.ui.button(label="🔊 Join Voice", style=discord.ButtonStyle.gray, custom_id="help_join_voice")
    async def join_voice(self, interaction: discord.Interaction, button: discord.ui.Button):
        await interaction.response.send_message("🔊 Select a channel to join:", view=VoiceJoinSelectView(), ephemeral=True)

    @discord.ui.button(label="👋 Leave Voice", style=discord.ButtonStyle.danger, custom_id="help_leave_voice")
    async def leave_voice(self, interaction: discord.Interaction, button: discord.ui.Button):
        if interaction.guild.voice_client:
            await interaction.guild.voice_client.disconnect()
            await interaction.response.send_message("👋 Disconnected", ephemeral=True)
        else:
            await interaction.response.send_message("❌ I am not connected to voice.", ephemeral=True)

class Bot(discord.Client):
    def __init__(self):
        intents = discord.Intents.default()
        intents.message_content = True # Needed if we react to messages later
        intents.members = True  # Needed for DMs
        super().__init__(intents=intents)
        self.start_time = time.time()  # Track uptime
        self.tree = app_commands.CommandTree(self)
        self.web_server_started = False

    async def setup_hook(self):
        # Register Persistent Views
        self.add_view(HelpView())
        
        # Register Commands
        
        # /join
        @self.tree.command(name="join", description="Join your current voice channel")
        async def join(interaction: discord.Interaction):
            if not interaction.user.voice or not interaction.user.voice.channel:
                await interaction.response.send_message("❌ You are not in a voice channel.", ephemeral=True)
                return
            
            channel = interaction.user.voice.channel
            try:
                if interaction.guild.voice_client:
                    await interaction.guild.voice_client.move_to(channel)
                else:
                    await channel.connect()
                await interaction.response.send_message(f"✅ Joined {channel.mention}")
            except Exception as e:
                await interaction.response.send_message(f"❌ Error joining: {e}", ephemeral=True)

        # /leave
        @self.tree.command(name="leave", description="Disconnect from voice channel")
        async def leave(interaction: discord.Interaction):
            if interaction.guild.voice_client:
                await interaction.guild.voice_client.disconnect()
                await interaction.response.send_message("👋 Disconnected", ephemeral=True)
            else:
                await interaction.response.send_message("❌ I am not connected to voice.", ephemeral=True)

        # /event create
        @self.tree.command(name="event_create", description="Create a new event")
        @check_auth('event_create')
        async def event_create(interaction: discord.Interaction):
            await interaction.response.send_modal(EventModal())

        # /embed - Send embed message with mention support
        @self.tree.command(name="embed", description="Send an embed message with optional mentions")
        @check_auth('embed')
        async def embed(interaction: discord.Interaction):
            await interaction.response.send_modal(EmbedModal())

        @self.tree.command(name="send", description="Send a message with optional image and mentions")
        @check_auth('send')
        async def send(interaction: discord.Interaction):
            await interaction.response.send_modal(SendMessageModal())

        # /help - Show interactive help menu
        @self.tree.command(name="help", description="Show bot commands and dashboard")
        async def help_command(interaction: discord.Interaction):
            embed = discord.Embed(
                title="🤖 Bot Command Dashboard",
                description="Click the buttons below to interact with the bot.",
                color=discord.Color.from_str("#5865F2")
            )
            embed.set_thumbnail(url=self.user.display_avatar.url)
            embed.add_field(name="📅 Event Management", value="Create calendar events with RSVP support.", inline=False)
            embed.add_field(name="📢 Announcements", value="Send fancy embeds or simple messages as the bot.", inline=False)
            embed.add_field(name="🔊 Voice Control", value="Join or leave voice channels.", inline=False)
            
            await interaction.response.send_message(embed=embed, view=HelpView())

        # Sync Commands (Global)
        print("Syncing specific slash commands...", flush=True)
        try:
            await self.tree.sync()
            print("✅ Slash commands synced!", flush=True)
        except Exception as e:
            print(f"⚠️ Sync failed: {e}", flush=True)

        self.check_reminders.start()

    @tasks.loop(minutes=1)
    async def check_reminders(self):
        try:
            # 1. Fetch upcoming interviews from PHP API
            async with aiohttp.ClientSession() as session:
                async with session.get('http://web/api/internal_interviews.php?action=get_accepted_interviews') as resp:
                    if resp.status == 200:
                        data = await resp.json()
                        if data.get('success'):
                            interviews = data.get('data', [])
                            now = datetime.now()
                            
                            for interview in interviews:
                                try:
                                    # Parse Time
                                    # Expected format: YYYY-MM-DD HH:MM
                                    # If Thai format... we might need care. Assuming standard for now as extracted by API.
                                    # Parse Time
                                    it_time = None
                                    # Try common formats
                                    for fmt in ['%Y-%m-%d %H:%M', '%Y-%m-%d %H:%M:%S', '%d/%m/%Y %H:%M']:
                                        try:
                                            it_time = datetime.strptime(interview['interview_time_str'], fmt)
                                            break
                                        except ValueError:
                                            continue
                                            
                                    if not it_time:
                                        # print(f"Could not parse time: {interview['interview_time_str']}")
                                        continue
                                    diff = (it_time - now).total_seconds() / 60
                                    
                                    response_id = interview['id']
                                    discord_id_or_name = interview['discord_id']
                                    
                                    # REMINDER 1: 15 Minutes Before (10-20 min window)
                                    if 10 <= diff <= 20:
                                        # Check if already sent (Redis)
                                        cache_key = f"reminder:15min:{response_id}"
                                        if not r.get(cache_key):
                                            # Send DM
                                            # Try to resolve user
                                            user = None
                                            # Try finding by ID first
                                            if re.match(r'^\d+$', str(discord_id_or_name)):
                                                try:
                                                    user = await self.fetch_user(int(discord_id_or_name))
                                                except:
                                                    user = self.get_user(int(discord_id_or_name))
                                            
                                            if user:
                                                try:
                                                    embed = discord.Embed(title="🔔 Interview Reminder", description=f"Your interview for **{interview['form_title']}** is starting in 15 minutes!", color=discord.Color.gold())
                                                    embed.add_field(name="Time", value=f"<t:{int(it_time.timestamp())}:F>")
                                                    await user.send(embed=embed)
                                                    r.setex(cache_key, 3600, "sent") # Cache for 1 hour
                                                    print(f"Sent 15min reminder to {user.name}", flush=True)
                                                except Exception as dm_err:
                                                    print(f"DM Error: {dm_err}", flush=True)

                                    # REMINDER 2: At Time (0-5 min window) -> Welcome Channel
                                    if 0 <= diff <= 5:
                                        cache_key = f"reminder:now:{response_id}"
                                        if not r.get(cache_key):
                                            # Send to Welcome Channel
                                            # webhook_url_welcome actually stores Channel ID, not webhook URL
                                            channel_id_str = interview.get('webhook_url_welcome', '')
                                            if channel_id_str:
                                                channel = None
                                                # Try direct Channel ID first (most common case)
                                                if str(channel_id_str).isdigit():
                                                    channel = self.get_channel(int(channel_id_str))
                                                # Fallback: Try to extract from webhook URL format
                                                elif '/webhooks/' in str(channel_id_str):
                                                    match = re.search(r'/webhooks/(\d+)', str(channel_id_str))
                                                    if match:
                                                        channel = self.get_channel(int(match.group(1)))
                                                
                                                if channel:
                                                    mention = f"<@{discord_id_or_name}>" if str(discord_id_or_name).isdigit() else discord_id_or_name
                                                    embed = discord.Embed(
                                                        title="📢 เริ่มการสัมภาษณ์แล้ว!",
                                                        description=f"การสัมภาษณ์สำหรับ {mention} (**{interview['form_title']}**) เริ่มต้นแล้ว!",
                                                        color=discord.Color.green()
                                                    )
                                                    embed.add_field(name="⏰ เวลา", value=f"<t:{int(it_time.timestamp())}:F>", inline=False)
                                                    embed.set_footer(text="STORM Application System")
                                                    await channel.send(content=mention, embed=embed)
                                                    r.setex(cache_key, 3600, "sent")
                                                    print(f"Sent channel reminder for interview #{response_id}", flush=True)
                                except Exception as e:
                                    print(f"Error processing reminder {interview.get('id')}: {e}")

        except Exception as e:
            print(f"Reminder Loop Error: {e}")

    @check_reminders.before_loop
    async def before_reminders(self):
        await self.wait_until_ready()

    async def on_interaction(self, interaction: discord.Interaction):
        try:
            if interaction.type == discord.InteractionType.component:
                custom_id = interaction.data.get('custom_id')
                
                # --- APP SELECT MENU ---
                if custom_id and custom_id.startswith('app_select:'):
                    response_id = custom_id.split(':')[1]
                    selected_val = interaction.data['values'][0]
                    
                    if selected_val == 'approve':
                        # Update status to accepted via PHP
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
                        # Fetch panel config from PHP backend
                        async with aiohttp.ClientSession() as session:
                            async with session.get(f'http://web/admin/ticket_actions.php?action=get_panel&id={panel_id}') as resp:
                                panel_data = await resp.json()

                        if not panel_data.get('success'):
                            await interaction.followup.send('❌ Ticket panel not found.', ephemeral=True)
                            return

                        panel = panel_data['panel']

                        # Check max tickets
                        async with aiohttp.ClientSession() as session:
                            async with session.get(f'http://web/admin/ticket_actions.php?action=list_tickets&panel_id={panel_id}&status=open') as resp:
                                tickets_data = await resp.json()

                        user_open = sum(1 for t in tickets_data.get('tickets', []) if t.get('user_id') == str(interaction.user.id))
                        max_tickets = int(panel.get('max_tickets', 1))

                        if user_open >= max_tickets:
                            await interaction.followup.send(f'❌ You already have {user_open} open ticket(s). Maximum is {max_tickets}.', ephemeral=True)
                            return

                        # Create ticket channel via bot API (self)
                        guild = interaction.guild
                        member = interaction.user
                        category_id = panel.get('category_id')
                        support_role_id = panel.get('support_role_id')
                        welcome_msg = panel.get('welcome_message', '')

                        # Build overwrites
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

                        # Send welcome embed in ticket channel
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

                        # Save ticket to DB via PHP
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
                        # Close ticket in DB via PHP
                        async with aiohttp.ClientSession() as session:
                            await session.post('http://web/admin/ticket_actions.php', data={
                                'action': 'close_ticket_by_channel',
                                'channel_id': target_channel_id,
                                'closed_by': interaction.user.display_name
                            })

                        # Send closing message
                        closing_embed = discord.Embed(
                            title='🔒 Ticket Closed',
                            description=f'This ticket has been closed by {interaction.user.mention}.\nThis channel will be deleted in 5 seconds.',
                            color=0xf04747,
                            timestamp=datetime.now()
                        )
                        await interaction.followup.send(embed=closing_embed)

                        # Delete channel after delay
                        await asyncio.sleep(5)
                        channel = self.get_channel(int(target_channel_id))
                        if channel:
                            await channel.delete(reason=f'Ticket closed by {interaction.user.display_name}')

                    except Exception as e:
                        print(f"[ERROR] ticket_close interaction: {e}")
                        await interaction.followup.send(f'❌ Error closing ticket: {str(e)}', ephemeral=True)

        except Exception as e:
            print(f"Interaction Error: {e}")


    async def on_ready(self):
        print(f'Logged in as {self.user} (ID: {self.user.id})')
        print('------')
        
        # Ensure Database Schema
        try:
            print("Checking Database Schema...")
            DatabaseService.ensure_schema()
            DatabaseService.ensure_reminder_schema()
        except Exception as e:
             print(f"Schema Check Failed: {e}")

        # Start Background Reminder Task (for Events)
        if not self.web_server_started:
            self.loop.create_task(self.reminder_loop())
            # Start Interview Reminder Task
            self.loop.create_task(self.interview_reminder_loop())

        # Start Web Server
        if not self.web_server_started:
            try:
                # Increase max size to 50MB for large base64 images
                app = web.Application(client_max_size=50 * 1024 * 1024, middlewares=[api_key_middleware])
                app.router.add_get('/health', self.handle_health_check)
                app.router.add_post('/webhook', self.handle_legacy_webhook)
                app.router.add_post('/embed/send', self.handle_embed_request)
                app.router.add_post('/embed/edit', self.handle_embed_edit)
                app.router.add_post('/event/create', self.handle_event_request)
                app.router.add_post('/event/cancel', self.handle_event_cancel)
                app.router.add_get('/events/active', self.handle_get_active_events)
                app.router.add_get('/event/rsvp', self.handle_get_rsvp)
                app.router.add_get('/channels', self.handle_get_channels)
                app.router.add_get('/roles', self.handle_get_roles)
                app.router.add_get('/members', self.handle_get_members)
                # Bot Management Routes
                app.router.add_get('/bot/status', self.handle_get_bot_status)
                app.router.add_post('/bot/settings', self.handle_update_bot_settings)
                # Voice Channel Routes
                app.router.add_post('/bot/join', self.handle_join_voice)
                app.router.add_post('/bot/leave', self.handle_leave_voice)
                app.router.add_get('/voice_channels', self.handle_get_voice_channels)
                # Welcome Sound Routes
                app.router.add_get('/settings/welcome-sound', self.handle_get_welcome_sound)
                app.router.add_post('/settings/welcome-sound', self.handle_set_welcome_sound)
                # Server Welcome Routes
                app.router.add_get('/settings/server-welcome', self.handle_get_server_welcome)
                app.router.add_post('/settings/server-welcome', self.handle_set_server_welcome)
                app.router.add_post('/settings/server-welcome/test', self.handle_test_server_welcome)
                # Permission Routes
                app.router.add_get('/settings/permissions', self.handle_get_permissions)
                app.router.add_post('/settings/permissions', self.handle_save_permissions)
                # Saved Messages Routes (Message Builder)
                app.router.add_get('/messages', self.handle_list_messages)
                app.router.add_get('/messages/{id}', self.handle_get_message)
                app.router.add_post('/messages', self.handle_save_message)
                app.router.add_put('/messages/{id}', self.handle_update_message)
                app.router.add_delete('/messages/{id}', self.handle_delete_message)
                # Discord Feed Routes
                app.router.add_get('/feed/settings', self.handle_get_feed_settings)
                app.router.add_post('/feed/settings', self.handle_save_feed_settings)
                app.router.add_get('/feed/messages', self.handle_get_feed_messages)
                # Role Assignment Route (for application acceptance)
                app.router.add_post('/roles/assign', self.handle_assign_role)
                # Ticket System Routes
                app.router.add_post('/tickets/panel/send', self.handle_ticket_panel_send)
                app.router.add_post('/tickets/create', self.handle_ticket_create)
                app.router.add_post('/tickets/close', self.handle_ticket_close)
                app.router.add_get('/categories', self.handle_get_categories)
                # User Lookup Route (for @mention by username)
                app.router.add_get('/users/lookup', self.handle_lookup_user)
                # User Filtered Route (for form applicant selection - no role users + additional roles)
                app.router.add_get('/users/filtered', self.handle_get_filtered_users)
                
                runner = web.AppRunner(app)
                await runner.setup()
                site = web.TCPSite(runner, '0.0.0.0', PORT)
                print(f"Starting Web Server on port {PORT}")
                await site.start()
                self.web_server_started = True
            except Exception as e:
                print(f"Failed to start web server: {e}")

    async def reminder_loop(self):
        """Background task to check and send reminders"""
        await self.wait_until_ready()
        print("🔔 Reminder Loop Started", flush=True)
        
        while not self.is_closed():
            try:
                pending = DatabaseService.get_pending_reminders()
                for event in pending:
                    await self.send_event_reminder(event)
            except Exception as e:
                print(f"Reminder Loop Error: {e}", flush=True)
            
            await asyncio.sleep(60)  # Check every 60 seconds

    async def send_event_reminder(self, event):
        """Send reminder to accepted users via thread and DM"""
        try:
            event_id = event.get('event_id')
            title = event.get('title', 'Event')
            reminder_min = event.get('trigger_reminder_min', 0)
            accepted_users = event.get('accepted_users', [])
            channel_id = event.get('channel_id')
            message_id = event.get('message_id')
            
            print(f"🔔 Sending {reminder_min}min reminder for: {title}", flush=True)
            
            # Build mention list
            mentions = []
            for user in accepted_users:
                user_id = user.get('user_id')
                if user_id:
                    mentions.append(f"<@{user_id}>")
            
            mention_str = " ".join(mentions) if mentions else "No one has accepted yet"
            
            # Send reminder in thread/channel
            if channel_id:
                channel = self.get_channel(int(channel_id))
                if channel:
                    reminder_msg = f"⏰ **Reminder!** Event **{title}** starts in {reminder_min} minutes!\n{mention_str}"
                    
                    # Try to reply to original message
                    if message_id:
                        try:
                            original_msg = await channel.fetch_message(int(message_id))
                            await original_msg.reply(reminder_msg)
                        except:
                            await channel.send(reminder_msg)
                    else:
                        await channel.send(reminder_msg)
            
            # Send DM to each accepted user
            for user in accepted_users:
                user_id = user.get('user_id')
                if user_id:
                    try:
                        discord_user = await self.fetch_user(int(user_id))
                        if discord_user:
                            dm_msg = f"⏰ **Reminder!** Event **{title}** starts in {reminder_min} minutes!"
                            await discord_user.send(dm_msg)
                    except Exception as dm_err:
                        print(f"DM Error (user {user_id}): {dm_err}", flush=True)
            
            # Mark reminder as sent
            DatabaseService.mark_reminder_sent(event_id, reminder_min)
            print(f"✅ Reminder sent for {title} ({reminder_min}min)", flush=True)
            
        except Exception as e:
            print(f"Send Reminder Error: {e}", flush=True)

    async def interview_reminder_loop(self):
        """Background task to check and send interview reminders"""
        await self.wait_until_ready()
        print("📋 Interview Reminder Loop Started", flush=True)
        
        while not self.is_closed():
            try:
                # Fetch accepted interviews from PHP API
                async with aiohttp.ClientSession() as session:
                    async with session.get('http://web/api/internal_interviews.php?action=get_accepted_interviews') as resp:
                        if resp.status == 200:
                            data = await resp.json()
                            if data.get('success'):
                                interviews = data.get('data', [])
                                now = datetime.now()
                                
                                for interview in interviews:
                                    await self.process_interview_reminder(interview, now)
            except Exception as e:
                print(f"Interview Reminder Loop Error: {e}", flush=True)
            
            await asyncio.sleep(60)  # Check every 60 seconds

    async def process_interview_reminder(self, interview, now):
        """Process a single interview for reminders"""
        try:
            response_id = interview.get('id')
            discord_id = interview.get('discord_id', '')
            interview_str = interview.get('interview_time_str', '')
            form_title = interview.get('form_title', 'Application')
            webhook_url = interview.get('webhook_url_welcome', '')
            
            if not interview_str or not discord_id:
                return
            
            # Parse interview time (try multiple formats)
            interview_time = None
            for fmt in ['%Y-%m-%d %H:%M', '%Y-%m-%d %H:%M:%S', '%d/%m/%Y %H:%M', '%d-%m-%Y %H:%M']:
                try:
                    interview_time = datetime.strptime(interview_str, fmt)
                    break
                except ValueError:
                    continue
            
            if not interview_time:
                return
            
            # Calculate time difference in minutes
            diff_minutes = (interview_time - now).total_seconds() / 60
            
            # --- 15 MINUTE DM REMINDER ---
            if 10 <= diff_minutes <= 20:
                cache_key = f"interview:15min:{response_id}"
                if not r.get(cache_key):
                    await self.send_interview_dm_reminder(discord_id, form_title, interview_time, 15)
                    r.setex(cache_key, 7200, "sent")  # Cache for 2 hours
                    print(f"📋 Sent 15min DM reminder for interview #{response_id}", flush=True)
            
            # --- NOW REMINDER (Welcome Channel) ---
            if -5 <= diff_minutes <= 5:
                cache_key = f"interview:now:{response_id}"
                if not r.get(cache_key):
                    await self.send_interview_channel_reminder(discord_id, form_title, webhook_url)
                    r.setex(cache_key, 7200, "sent")  # Cache for 2 hours
                    print(f"📋 Sent channel reminder for interview #{response_id}", flush=True)
                    
        except Exception as e:
            print(f"Process Interview Reminder Error: {e}", flush=True)

    async def send_interview_dm_reminder(self, discord_id, form_title, interview_time, minutes_before):
        """Send DM reminder to applicant before interview"""
        try:
            # Get user
            user = None
            if discord_id.isdigit():
                try:
                    user = await self.fetch_user(int(discord_id))
                except:
                    pass
            
            if user:
                embed = discord.Embed(
                    title="🔔 การแจ้งเตือนสัมภาษณ์ (Interview Reminder)",
                    description=f"การสัมภาษณ์ของคุณสำหรับ **{form_title}** จะเริ่มในอีก **{minutes_before} นาที**!",
                    color=discord.Color.gold()
                )
                embed.add_field(name="⏰ เวลา", value=f"<t:{int(interview_time.timestamp())}:F>", inline=False)
                embed.add_field(name="📝 หมายเหตุ", value="กรุณาเตรียมตัวให้พร้อมและรอในช่องเสียงที่กำหนด", inline=False)
                embed.set_footer(text="STORM Application System")
                
                await user.send(embed=embed)
                
        except Exception as e:
            print(f"Interview DM Error (user {discord_id}): {e}", flush=True)

    async def send_interview_channel_reminder(self, discord_id, form_title, channel_id_or_url):
        """Send reminder to Welcome Channel when interview time arrives"""
        try:
            channel = None
            channel_id_str = str(channel_id_or_url) if channel_id_or_url else ''
            
            # Try direct Channel ID first (most common case - webhook_url_welcome stores Channel ID)
            if channel_id_str.isdigit():
                channel = self.get_channel(int(channel_id_str))
            # Fallback: Try to extract from webhook URL format
            elif '/webhooks/' in channel_id_str:
                match = re.search(r'/webhooks/(\d+)', channel_id_str)
                if match:
                    try:
                        channel = self.get_channel(int(match.group(1)))
                    except:
                        pass
            
            if channel:
                mention = f"<@{discord_id}>" if discord_id.isdigit() else discord_id
                embed = discord.Embed(
                    title="📢 เริ่มการสัมภาษณ์แล้ว!",
                    description=f"การสัมภาษณ์สำหรับ {mention} (**{form_title}**) เริ่มต้นแล้ว!",
                    color=discord.Color.green()
                )
                embed.add_field(name="⏰ เวลา", value=f"<t:{int(datetime.now().timestamp())}:F>", inline=False)
                embed.set_footer(text="STORM Application System")
                
                await channel.send(content=mention, embed=embed)
                
        except Exception as e:
            print(f"Interview Channel Reminder Error: {e}", flush=True)

    # --- HEALTH CHECK ---
    
    # --- PERMISSIONS API ---

    async def handle_get_permissions(self, request):
        """Get all permissions"""
        try:
            perms = PermissionService.load_permissions()
            return web.json_response({'success': True, 'permissions': perms})
        except Exception as e:
            return web.json_response({'error': str(e)}, status=500)

    async def handle_save_permissions(self, request):
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

    async def handle_health_check(self, request):
        """Simple health check endpoint (no auth required)"""
        return web.json_response({
            'status': 'ok',
            'bot_ready': self.is_ready(),
            'latency_ms': round(self.latency * 1000, 2) if self.is_ready() else None
        })

    # --- BOT MANAGEMENT ---

    async def handle_get_bot_status(self, request):
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
                'activity_type': activity_type,
                'activity_text': activity_text,
                'current_voice_channel': current_voice_channel
            }
            return web.json_response({'success': True, 'data': status_data})
        except Exception as e:
            print(f"Bot Status Error: {e}", flush=True)
            return web.json_response({'success': False, 'error': str(e)}, status=500)

    async def handle_update_bot_settings(self, request):
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
            activity_text = data.get('activity_text')
            
            status_map = {
                'online': discord.Status.online,
                'idle': discord.Status.idle,
                'dnd': discord.Status.dnd,
                'invisible': discord.Status.invisible
            }
            
            activity_map = {
                'playing': discord.ActivityType.playing,
                'watching': discord.ActivityType.watching,
                'listening': discord.ActivityType.listening,
                'competing': discord.ActivityType.competing
            }
            
            discord_status = status_map.get(new_status, discord.Status.online)
            
            activity = None
            if activity_type and activity_text:
                act_type = activity_map.get(activity_type, discord.ActivityType.playing)
                activity = discord.Activity(type=act_type, name=activity_text)
            
            await self.change_presence(status=discord_status, activity=activity)
            print(f"✅ Presence updated: {new_status}, {activity_type}: {activity_text}", flush=True)
            
            return web.json_response({'success': True, 'message': 'Settings updated'})
            
        except Exception as e:
            print(f"Update Settings Error: {e}", flush=True)
            return web.json_response({'success': False, 'error': str(e)}, status=500)

    # --- VOICE HANDLERS ---

    async def handle_get_voice_channels(self, request):
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

    async def handle_join_voice(self, request):
        """Joins a voice channel."""
        try:
            data = await request.json()
            channel_id = data.get('channel_id')
            if not channel_id:
                return web.json_response({'error': 'Missing channel_id'}, status=400)

            channel = self.get_channel(int(channel_id))
            if not channel or not isinstance(channel, discord.VoiceChannel):
                return web.json_response({'error': 'Voice channel not found'}, status=404)

            if channel.guild.voice_client:
                if channel.guild.voice_client.channel.id != channel.id:
                    await channel.guild.voice_client.move_to(channel)
            else:
                await channel.connect()
            
            return web.json_response({'success': True, 'message': f'Joined {channel.name}'})
        except Exception as e:
            print(f"Join Voice Error: {e}")
            return web.json_response({'error': str(e)}, status=500)

    async def handle_leave_voice(self, request):
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

    async def handle_get_channels(self, request):
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

    async def handle_get_roles(self, request):
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

    async def handle_assign_role(self, request):
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

    async def handle_ticket_panel_send(self, request):
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

    async def handle_ticket_create(self, request):
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

    async def handle_ticket_close(self, request):
        """Close/delete a ticket channel."""
        try:
            data = await request.json()
            channel_id = int(data.get('channel_id', 0))

            channel = self.get_channel(channel_id)
            if channel:
                await channel.delete(reason='Ticket closed')

            return web.json_response({'success': True})

        except Exception as e:
            print(f"[ERROR] handle_ticket_close: {e}")
            return web.json_response({'success': False, 'error': str(e)}, status=500)

    async def handle_get_categories(self, request):
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

    async def handle_get_members(self, request):
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

    async def handle_get_rsvp(self, request):
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

    async def handle_embed_request(self, request):
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

    async def handle_event_request(self, request):
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

    async def handle_get_active_events(self, request):
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

    async def handle_event_cancel(self, request):
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

    async def handle_legacy_webhook(self, request):
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

    async def handle_embed_edit(self, request):
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

    async def handle_list_messages(self, request):
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

    async def handle_get_message(self, request):
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

    async def handle_save_message(self, request):
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

    async def handle_update_message(self, request):
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

    async def handle_delete_message(self, request):
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
    
    async def handle_get_welcome_sound(self, request):
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
    
    async def handle_set_welcome_sound(self, request):
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

    async def on_voice_state_update(self, member, before, after):
        """Called when a member changes voice state (join/leave/mute/etc.)"""
        # Ignore bot's own changes
        if member.bot:
            return
            
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

            channel = self.get_channel(int(channel_id))
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
                file_attachment = await self._download_banner_image(external_image)
                if not file_attachment:
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

    # --- FEED API HANDLERS ---
    
    async def handle_get_feed_settings(self, request):
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
    
    async def handle_save_feed_settings(self, request):
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
    
    async def handle_get_feed_messages(self, request):
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

    async def handle_lookup_user(self, request):
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

    async def handle_get_filtered_users(self, request):
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

    # --- BANNER IMAGE DOWNLOAD HELPER ---

    async def _download_banner_image(self, image_url: str):
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
                            # Get filename from URL or use default
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

    # --- SERVER WELCOME SETTINGS HANDLERS ---

    async def handle_get_server_welcome(self, request):
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

    async def handle_set_server_welcome(self, request):
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

    async def handle_test_server_welcome(self, request):
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
                file_attachment = await self._download_banner_image(banner_image)
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

client = Bot()

if __name__ == '__main__':
    if TOKEN:
        client.run(TOKEN)
    else:
        print("Error: DISCORD_BOT_TOKEN not found.")

