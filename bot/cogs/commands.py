import discord
from discord.ext import commands
from discord import app_commands
from services.permissions import PermissionService
from ui.components import EventModal, EmbedModal, SendMessageModal, HelpView

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

class GeneralCommands(commands.Cog):
    def __init__(self, bot: commands.Bot):
        self.bot = bot

    @app_commands.command(name="join", description="Join your current voice channel")
    async def join(self, interaction: discord.Interaction):
        if not interaction.user.voice or not interaction.user.voice.channel:
            await interaction.response.send_message("❌ You are not in a voice channel.", ephemeral=True)
            return
        channel = interaction.user.voice.channel
        try:
            if interaction.guild.voice_client:
                await interaction.guild.voice_client.move_to(channel)
            else:
                await channel.connect()
                await interaction.guild.change_voice_state(channel=channel, self_deaf=True)
            await interaction.response.send_message(f"✅ Joined {channel.mention}")
        except Exception as e:
            await interaction.response.send_message(f"❌ Error joining: {e}", ephemeral=True)

    @app_commands.command(name="leave", description="Disconnect from voice channel")
    async def leave(self, interaction: discord.Interaction):
        if interaction.guild.voice_client:
            await interaction.guild.voice_client.disconnect()
            await interaction.response.send_message("👋 Disconnected", ephemeral=True)
        else:
            await interaction.response.send_message("❌ I am not connected to voice.", ephemeral=True)

    @app_commands.command(name="event_create", description="Create a new event")
    @check_auth('event_create')
    async def event_create(self, interaction: discord.Interaction):
        await interaction.response.send_modal(EventModal())

    @app_commands.command(name="embed", description="Send an embed message with optional mentions")
    @check_auth('embed')
    async def embed(self, interaction: discord.Interaction):
        await interaction.response.send_modal(EmbedModal())

    @app_commands.command(name="send", description="Send a message with optional image and mentions")
    @check_auth('send')
    async def send(self, interaction: discord.Interaction):
        await interaction.response.send_modal(SendMessageModal(interaction.channel))

    @app_commands.command(name="help", description="Show bot commands and dashboard")
    async def help_command(self, interaction: discord.Interaction):
        embed = discord.Embed(
            title="🤖 Bot Command Dashboard",
            description="Click the buttons below to interact with the bot.",
            color=discord.Color.from_str("#5865F2")
        )
        embed.set_thumbnail(url=self.bot.user.display_avatar.url)
        embed.add_field(name="📅 Event Management", value="Create calendar events with RSVP support.", inline=False)
        embed.add_field(name="📢 Announcements", value="Send fancy embeds or simple messages as the bot.", inline=False)
        embed.add_field(name="🔊 Voice Control", value="Join or leave voice channels.", inline=False)
        
        await interaction.response.send_message(embed=embed, view=HelpView())

async def setup(bot: commands.Bot):
    await bot.add_cog(GeneralCommands(bot))
