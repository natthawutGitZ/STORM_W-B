import discord
from discord.ext import commands
import os
import time
from dotenv import load_dotenv

# Explicitly load Opus for voice support in Docker containers
if not discord.opus.is_loaded():
    try:
        discord.opus.load_opus('libopus.so.0')
        print("✅ Opus loaded successfully!", flush=True)
    except Exception as e:
        print(f"⚠️ Opus load failed: {e}", flush=True)

from services.database import DatabaseService

load_dotenv()
TOKEN = os.getenv('DISCORD_BOT_TOKEN')

class CoreBot(commands.Bot):
    def __init__(self):
        intents = discord.Intents.default()
        intents.message_content = True
        intents.members = True
        super().__init__(command_prefix="!", intents=intents)
        self.start_time = time.time()
        self.web_server_started = False

    async def setup_hook(self):
        # Register Persistent Views (if not handled dynamically by cogs)
        from ui.components import HelpView
        self.add_view(HelpView())

        # Load Cogs
        cogs_to_load = ['cogs.commands', 'cogs.tasks', 'cogs.events']
        for cog in cogs_to_load:
            try:
                await self.load_extension(cog)
                print(f"✅ Loaded extension {cog}", flush=True)
            except Exception as e:
                print(f"❌ Failed to load extension {cog}: {e}", flush=True)

        print("Syncing specific slash commands...", flush=True)
        try:
            await self.tree.sync()
            print("✅ Slash commands synced!", flush=True)
        except Exception as e:
            print(f"⚠️ Sync failed: {e}", flush=True)

    async def on_ready(self):
        print(f'Logged in as {self.user} (ID: {self.user.id})')
        print('------')
        
        # Ensure Database Schema
        try:
            print("Checking Database Schema...")
            DatabaseService.ensure_schema()
            DatabaseService.ensure_reminder_schema()
            DatabaseService.ensure_role_panels_schema()
        except Exception as e:
            print(f"Schema Check Failed: {e}")

        # Start Web Server via API module
        if not self.web_server_started:
            import api.server
            self.loop.create_task(api.server.start_server(self))

client = CoreBot()

if __name__ == '__main__':
    if TOKEN:
        client.run(TOKEN)
    else:
        print("Error: DISCORD_BOT_TOKEN not found.")
