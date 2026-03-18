import discord
import time
from datetime import datetime
import aiohttp

from services.embeds import EmbedService
from services.events import EventService, RSVPView
from services.database import DatabaseService

# --- DEPENDENCY HELPERS ---
# replace_mentions_in_text needs to be here because EmbedModal uses it
async def process_mentions(text: str, guild: discord.Guild) -> str:
    """Replace @Role and @User patterns with actual mentions"""
    if not text or not guild:
        return text
    
    roles = sorted(guild.roles, key=lambda r: len(r.name), reverse=True)
    for role in roles:
        pattern = f"@{role.name}"
        if pattern in text:
             text = text.replace(pattern, role.mention)
    
    members = sorted(guild.members, key=lambda m: len(m.display_name), reverse=True)
    for member in members:
        if f"@{member.display_name}" in text:
            text = text.replace(f"@{member.display_name}", member.mention)
        elif f"@{member.name}" in text:
            text = text.replace(f"@{member.name}", member.mention)
            
    return text

# --- UI COMPONENTS ---

class EventModal(discord.ui.Modal, title='Create New Event'):
    event_title = discord.ui.TextInput(label='Event Title', placeholder='e.g. Boss Fight', required=True)
    description = discord.ui.TextInput(label='Description', style=discord.TextStyle.paragraph, required=True)
    date_str = discord.ui.TextInput(label='Date (YYYY-MM-DD)', placeholder='2024-12-31', required=True)
    time_str = discord.ui.TextInput(label='Time (HH:MM)', placeholder='20:00', required=True)
    image_url = discord.ui.TextInput(label='Image URL (Optional)', required=False)

    async def on_submit(self, interaction: discord.Interaction):
        try:
            data = {
                'title': self.event_title.value,
                'description': self.description.value,
                'date': self.date_str.value,
                'time': self.time_str.value,
                'image': self.image_url.value if self.image_url.value else None,
                'color': '#faa61a',
                'visibility': {'channel_id': str(interaction.channel_id)}
            }
            
            embed = EventService.create_event_embed(data)
            event_id = f"evt_{int(time.time())}"
            view = RSVPView(event_id)
            
            await interaction.response.send_message("Creating event...", ephemeral=True)
            msg = await interaction.channel.send(embed=embed, view=view)
            
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
            channel = interaction.guild.get_channel(selected.id)
            if not channel:
                channel = await interaction.guild.fetch_channel(selected.id)
            await channel.send(content=self.content, embed=self.embed)
            await interaction.response.edit_message(content=f"✅ Sent to {channel.mention}", view=None, embed=None)
        except Exception as e:
            await interaction.response.edit_message(content=f"❌ Failed to send: {str(e)}", view=None)

class EmbedModal(discord.ui.Modal, title='Create Embed Message'):
    mention = discord.ui.TextInput(label='Mention (@role/@user)', placeholder='@everyone, @RoleName, @Username', required=False)
    embed_title = discord.ui.TextInput(label='Title', required=True)
    description = discord.ui.TextInput(label='Description', style=discord.TextStyle.paragraph, required=True)
    color_hex = discord.ui.TextInput(label='Color Only Hex (#RRGGBB)', placeholder='#00b0f4', required=False)
    image_url = discord.ui.TextInput(label='Image URL (Optional)', required=False)
    
    async def on_submit(self, interaction: discord.Interaction):
        try:
            color = discord.Color.blue()
            if self.color_hex.value:
                try: color = discord.Color.from_str(self.color_hex.value)
                except: pass
            
            embed = discord.Embed(title=self.embed_title.value, description=self.description.value, color=color, timestamp=datetime.now())
            if self.image_url.value:
                embed.set_image(url=self.image_url.value)
            
            mention_content = await process_mentions(self.mention.value, interaction.guild) if self.mention.value else None
            view = ChannelSelectView(content=mention_content, embed=embed)
            await interaction.response.send_message("Please select a channel to send this message to:", view=view, ephemeral=True)
        except Exception as e:
            await interaction.response.send_message(f"Error sending embed: {str(e)}", ephemeral=True)

class SendMessageModal(discord.ui.Modal, title='Send Message'):
    content = discord.ui.TextInput(label='Message', style=discord.TextStyle.paragraph, required=True)
    
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
                payload = {'response_id': self.response_id, 'date': self.new_date.value, 'time': self.new_time.value, 'reason': self.reason.value}
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

class VoiceJoinSelectView(discord.ui.View):
    def __init__(self):
        super().__init__(timeout=60)
    
    @discord.ui.select(cls=discord.ui.ChannelSelect, channel_types=[discord.ChannelType.voice, discord.ChannelType.stage_voice], placeholder="Select a voice channel to join...")
    async def select_voice(self, interaction: discord.Interaction, select: discord.ui.ChannelSelect):
        try:
            selected_channel = select.values[0]
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
        super().__init__(timeout=None)
        self.options_data = options_data if options_data else []
        options = []
        for i, opt in enumerate(self.options_data):
            label = opt.get('label', f'Option {i+1}')
            options.append(discord.SelectOption(label=label, value=str(i)))
            
        if options:
            select = discord.ui.Select(placeholder="Select an option...", options=options)
            select.callback = self.select_callback
            self.add_item(select)

        if link_buttons:
            for btn in link_buttons:
                if btn.get('label') and btn.get('url'):
                     self.add_item(discord.ui.Button(label=btn['label'], url=btn['url']))

    async def select_callback(self, interaction: discord.Interaction):
        try:
            index = int(interaction.data['values'][0])
            if 0 <= index < len(self.options_data):
                response_text = self.options_data[index].get('response_text', 'No response configured.')
                await interaction.response.send_message(response_text, ephemeral=True)
            else:
                await interaction.response.send_message("❌ Invalid selection.", ephemeral=True)
        except Exception as e:
             await interaction.response.send_message(f"❌ Error: {str(e)}", ephemeral=True)

class HelpView(discord.ui.View):
    def __init__(self):
        super().__init__(timeout=None)

    @discord.ui.button(label="📅 Create Event", style=discord.ButtonStyle.primary, custom_id="help_create_event")
    async def create_event(self, interaction: discord.Interaction, button: discord.ui.Button):
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
