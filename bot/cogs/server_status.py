import discord
from discord.ext import commands, tasks
from services.database import DatabaseService
from datetime import datetime, timezone, timedelta
import a2s

BANGKOK_TZ = timezone(timedelta(hours=7))
import logging

log = logging.getLogger(__name__)

class ServerStatus(commands.Cog):
    def __init__(self, bot):
        self.bot = bot
        self.last_players = None
        self.update_server_status.start()

    def cog_unload(self):
        self.update_server_status.cancel()

    async def force_update(self):
        await self.update_server_status()

    @tasks.loop(seconds=60)
    async def update_server_status(self):
        try:
            config = DatabaseService.get_server_status_config()
            if not config or not config.get('is_active') or not config.get('channel_id'):
                return

            ip = config['ip']
            port = int(config['port'])
            channel_id = int(config['channel_id'])
            message_id = int(config['message_id']) if config.get('message_id') else None

            channel = self.bot.get_channel(channel_id)
            if not channel:
                # Fallback to fetch if not in cache
                try:
                    channel = await self.bot.fetch_channel(channel_id)
                except discord.NotFound:
                    print(f"[ServerStatus] Channel {channel_id} not found.", flush=True)
                    return

            try:
                # Query server via a2s
                info = await a2s.ainfo((ip, port), timeout=5.0)
                try:
                    players = await a2s.aplayers((ip, port), timeout=5.0)
                    
                    # Track joins and leaves
                    current_player_names = {p.name for p in players if getattr(p, 'name', None)}
                    if self.last_players is not None:
                        joins = current_player_names - self.last_players
                        leaves = self.last_players - current_player_names
                        
                        for p_name in joins:
                            DatabaseService.log_player_action(p_name, 'join')
                        for p_name in leaves:
                            DatabaseService.log_player_action(p_name, 'leave')
                            
                    self.last_players = current_player_names
                    
                except Exception:
                    # Sometimes players query fails or times out even if info succeeds
                    players = []
                    
                status_emoji = "✅"
                embed_color = 0x2ecc71 # Green
            except Exception as e:
                info = None
                players = []
                status_emoji = "❌ Offline"
                embed_color = 0xe74c3c # Red

            # Construct Embed
            embed = discord.Embed(color=embed_color)
            if info:
                # Use standard A2S fields
                server_name = info.server_name if info.server_name else f"Arma 3 Server"
                embed.title = f"{server_name} [{info.player_count}/{info.max_players}]"
                
                mission_name = info.game if info.game else "N/A"
                embed.description = f"**Mission:** \"{mission_name}\" || {status_emoji}\n"
                
                # Info Fields
                embed.add_field(name="MAP", value=info.map_name or "Unknown", inline=True)
                embed.add_field(name="IP / Port", value=f"{ip}:{port}", inline=True)
                embed.add_field(name="Game Version", value=info.version or "Unknown", inline=True)
                
                # Analyze / Performance section
                embed.add_field(name="------------------Analyze------------------", value="** **", inline=False)
                ping = round(info.ping * 1000) if hasattr(info, 'ping') else "N/A"
                embed.add_field(name="Server Ping", value=f"`{ping} ms`", inline=True)
                
                # Player section
                embed.add_field(name="------------------Player------------------", value="** **", inline=False)
                
                player_names = [p.name for p in players if p.name]
                if player_names:
                    # Discord embed field values are limited to 1024 characters
                    players_str = "\n".join(player_names)
                    if len(players_str) > 1000:
                        players_str = players_str[:997] + "..."
                    embed.add_field(name="Player", value=f"```\n{players_str}\n```", inline=False)
                else:
                    embed.add_field(name="Player", value="```\nNo players online\n```", inline=False)
            else:
                embed.title = f"Server Offline [{ip}:{port}]"
                embed.description = f"**Status:** {status_emoji}"

            embed.set_footer(text=f"System Time: {datetime.now(BANGKOK_TZ).strftime('%Y-%m-%d || %H:%M:%S')}")

            # Send or Edit Message
            if message_id:
                try:
                    msg = await channel.fetch_message(message_id)
                    await msg.edit(embed=embed)
                except discord.NotFound:
                    # Message was deleted, send a new one
                    msg = await channel.send(embed=embed)
                    DatabaseService.update_server_status_message_id(str(msg.id))
                except discord.Forbidden:
                    print("[ServerStatus] Missing permissions to edit/send message.", flush=True)
            else:
                msg = await channel.send(embed=embed)
                DatabaseService.update_server_status_message_id(str(msg.id))

        except Exception as e:
            print(f"[ERROR] server_status loop: {e}", flush=True)

    @update_server_status.before_loop
    async def before_update_server_status(self):
        await self.bot.wait_until_ready()

async def setup(bot):
    await bot.add_cog(ServerStatus(bot))
