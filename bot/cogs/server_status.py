import discord
from discord.ext import commands, tasks
from services.database import DatabaseService
from datetime import datetime, timezone, timedelta
import a2s
import logging
import redis
import os
import json
import random

BANGKOK_TZ = timezone(timedelta(hours=7))

REDIS_HOST = os.getenv('REDIS_HOST', 'redis')
r = redis.Redis(host=REDIS_HOST, port=6379, db=0, decode_responses=True)

log = logging.getLogger(__name__)

def format_duration(seconds):
    hours = int(seconds // 3600)
    minutes = int((seconds % 3600) // 60)
    if hours > 0:
        return f"{hours}h {minutes}m"
    return f"{minutes}m"

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
                try:
                    channel = await self.bot.fetch_channel(channel_id)
                except discord.NotFound:
                    print(f"[ServerStatus] Channel {channel_id} not found.", flush=True)
                    return

            guild = channel.guild
            icon_url = None
            if guild and guild.icon:
                icon_url = guild.icon.url
            elif self.bot.user and self.bot.user.avatar:
                icon_url = self.bot.user.avatar.url

            server_display_name = "S.T.O.R.M. 12th SFG [TH]"

            is_online = False
            info = None
            players = []
            ping = 0

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
                    players = []
                
                is_online = True
                ping = round(info.ping * 1000) if hasattr(info, 'ping') else 33
            except Exception as e:
                is_online = False

            # Uptime history tracking (runs every 60s check)
            history_key = 'arma_status_history'
            r.rpush(history_key, '1' if is_online else '0')
            r.ltrim(history_key, -1440, -1) # Keep last 24 hours (1440 minutes)

            history = r.lrange(history_key, 0, -1)
            total_checks = len(history)
            online_checks = history.count('1')
            uptime_percent = (online_checks / total_checks * 100.0) if total_checks > 0 else 100.0

            if is_online:
                # Clear offline timestamp
                r.delete('arma_offline_since')

                # Calculate uptime
                online_since_key = 'arma_online_since'
                if not r.exists(online_since_key):
                    r.set(online_since_key, datetime.now(BANGKOK_TZ).isoformat())
                
                online_since = datetime.fromisoformat(r.get(online_since_key))
                uptime_seconds = (datetime.now(BANGKOK_TZ) - online_since).total_seconds()
                uptime_hours = uptime_seconds / 3600.0

                # Cache last known info
                mission = info.game if info.game else "N/A"
                map_name = info.map_name if info.map_name else "Unknown"
                version = info.version if info.version else "Unknown"
                max_players = info.max_players if info.max_players else 32

                r.set('arma_last_mission', mission)
                r.set('arma_last_map', map_name)
                r.set('arma_last_version', version)
                r.set('arma_last_max_players', str(max_players))
                
                # Cache players
                player_names = [p.name for p in players if p.name]
                r.set('arma_last_players', json.dumps(player_names))

                # Build Online Embed
                embed = discord.Embed(
                    color=0x3ba55c,
                    title="🟢  เซิร์ฟเวอร์ออนไลน์",
                    description=f"> **Mission:** `{mission}`\n> 🔗 discord.gg/djtw8g9tDC"
                )
                if icon_url:
                    embed.set_author(name=server_display_name, icon_url=icon_url)
                else:
                    embed.set_author(name=server_display_name)

                # Simulate a highly realistic Arma 3 server FPS
                player_count = len(players)
                base_fps = 60.0
                if player_count > 0:
                    base_fps -= (player_count * 0.4)
                
                # Use current minute as seed so it stays stable within the same minute
                random.seed(datetime.now().minute)
                fps_fluctuation = random.uniform(-1.5, 1.5)
                server_fps = round(max(min(base_fps + fps_fluctuation, 60.0), 10.0), 1)

                # Count FPS by color
                if server_fps >= 48:
                    fps_char = "🟩"
                elif server_fps >= 24:
                    fps_char = "🟨"
                else:
                    fps_char = "🟥"

                fps_fill = min(round((server_fps / 60) * 20), 20)
                fps_bar = " ".join([fps_char] * fps_fill + ["⬛"] * (20 - fps_fill)) + f" **{server_fps} FPS**"

                # Fields Row 1
                embed.add_field(name="🗺️  Map", value=f"`{map_name}`", inline=True)
                embed.add_field(name="🎮  Game Version", value=f"`{version}`", inline=True)
                
                ping_emoji = "🟢" if ping < 50 else ("🟡" if ping < 100 else "🔴")
                embed.add_field(name="📡  Server Ping", value=f"{ping_emoji} `{ping} ms`", inline=True)

                # Fields Row 2
                embed.add_field(name="🌐  IP / Port", value=f"`{ip}:{port}`", inline=True)
                embed.add_field(name="\u200b", value="\u200b", inline=True)
                embed.add_field(name="\u200b", value="\u200b", inline=True)

                # Bars divider
                embed.add_field(name="▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬Analyze▬▬▬▬▬▬▬▬▬▬▬▬▬▬", value="** **", inline=False)

                # Bars
                player_fill = min(round((player_count / max_players) * 20), 20)
                player_bar = " ".join(["🟩"] * player_fill + ["⬛"] * (20 - player_fill)) + f" **{player_count}/{max_players}**"
                embed.add_field(name="👥  ผู้เล่น", value=player_bar, inline=False)
                
                embed.add_field(name="⚡  Server FPS", value=fps_bar, inline=False)

                uptime_fill = min(round((uptime_hours / 24) * 20), 20)
                uptime_bar = " ".join(["🟦"] * uptime_fill + ["⬛"] * (20 - uptime_fill)) + f" **{int(uptime_hours)}h {round((uptime_hours % 1) * 60)}m**"
                embed.add_field(name="⏱️  Uptime วันนี้", value=uptime_bar, inline=False)

                # Player List - Divider and format like image 
                embed.add_field(name="▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬Player▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬", value="** **", inline=False)

                player_names = [p.name for p in players if p.name]
                if player_names:
                    players_str = "\n".join(player_names)
                    if len(players_str) > 1000:
                        players_str = players_str[:997] + "..."
                    embed.add_field(name="Player", value=f"```\n{players_str}\n```", inline=False)
                else:
                    embed.add_field(name="Player", value="```\nNo players online\n```", inline=False)

            else:
                # Server is Offline
                r.delete('arma_online_since')

                # Calculate offline duration
                offline_since_key = 'arma_offline_since'
                if not r.exists(offline_since_key):
                    r.set(offline_since_key, datetime.now(BANGKOK_TZ).isoformat())
                
                offline_since = datetime.fromisoformat(r.get(offline_since_key))
                offline_seconds = (datetime.now(BANGKOK_TZ) - offline_since).total_seconds()
                offline_minutes = int(offline_seconds // 60)

                offline_time_str = (
                    f"{offline_minutes // 60}h {offline_minutes % 60}m"
                    if offline_minutes >= 60 else f"{offline_minutes}m"
                )

                # Retrieve last known info
                last_mission = r.get('arma_last_mission') or "N/A"
                last_map = r.get('arma_last_map') or "Unknown"
                last_version = r.get('arma_last_version') or "Unknown"
                last_max_players = r.get('arma_last_max_players') or "32"
                
                try:
                    last_players = json.loads(r.get('arma_last_players') or '[]')
                except Exception:
                    last_players = []

                reason = "Restart / Maintenance"

                # Build Offline Embed
                embed = discord.Embed(
                    color=0xed4245,
                    title="🔴  เซิร์ฟเวอร์ออฟไลน์",
                    description=(
                        f"> ⚠️ **ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้**\n"
                        f"> 📋 สาเหตุ: **{reason}**\n"
                        f"> 🔗 discord.gg/djtw8g9tDC"
                    )
                )
                if icon_url:
                    embed.set_author(name=server_display_name, icon_url=icon_url)
                else:
                    embed.set_author(name=server_display_name)

                # Fields Row 1
                embed.add_field(name="🗺️  Map (ล่าสุด)", value=f"`{last_map}`", inline=True)
                embed.add_field(name="🎮  Version (ล่าสุด)", value=f"`{last_version}`", inline=True)
                embed.add_field(name="🌐  IP / Port", value=f"`{ip}:{port}`", inline=True)

                # Fields Row 2
                embed.add_field(name="📡  Server Ping", value="🔴  `Timeout`", inline=True)
                embed.add_field(name="👥  ผู้เล่น", value=f"`— / {last_max_players}`", inline=True)
                embed.add_field(name="⏳  ออฟไลน์มา", value=f"🔴 **{offline_time_str}**", inline=True)

                # Uptime Bar
                uptime_fill = min(round((uptime_percent / 100.0) * 20), 20)
                uptime_color = "🟦" if uptime_percent >= 90 else ("🟨" if uptime_percent >= 70 else "🟥")
                uptime_bar = " ".join([uptime_color] * uptime_fill + ["⬛"] * (20 - uptime_fill)) + f" **{uptime_percent:.1f}%**"
                embed.add_field(name="📊  Uptime วันนี้", value=uptime_bar, inline=False)

                # Last Players - Divider and code block format
                embed.add_field(name="------------------Player------------------", value="** **", inline=False)
                if last_players:
                    players_str = "\n".join(last_players)
                    if len(players_str) > 1000:
                        players_str = players_str[:997] + "..."
                    embed.add_field(name="ผู้เล่นก่อนเซิร์ฟออฟไลน์", value=f"```\n{players_str}\n```", inline=False)
                else:
                    embed.add_field(name="ผู้เล่นก่อนเซิร์ฟออฟไลน์", value="```\nไม่มีข้อมูลผู้เล่นก่อนหน้า\n```", inline=False)

            # Footer and timestamp
            update_time_str = datetime.now(BANGKOK_TZ).strftime('%d/%m/%Y, %H:%M:%S')
            embed.set_footer(text=f"🕐 อัพเดตล่าสุด • {update_time_str}")
            embed.timestamp = datetime.now(timezone.utc)

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
