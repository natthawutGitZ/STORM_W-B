import discord
from discord.ext import commands, tasks
import json
import redis
import os
import asyncio
from datetime import datetime, timezone, timedelta
import aiohttp
import re

from services.database import DatabaseService

# Bangkok timezone (UTC+7)
BANGKOK_TZ = timezone(timedelta(hours=7))
REDIS_HOST = os.getenv('REDIS_HOST', 'redis')
r = redis.Redis(host=REDIS_HOST, port=6379, db=0, decode_responses=True)

class BackgroundTasks(commands.Cog):
    def __init__(self, bot: commands.Bot):
        self.bot = bot
        # Initialize properties on bot to be accessible by API routes
        if not hasattr(self.bot, 'activity_texts_cache'):
            self.bot.activity_texts_cache = []
        if not hasattr(self.bot, 'activity_type_cache'):
            self.bot.activity_type_cache = 'playing'
        if not hasattr(self.bot, 'current_activity_index'):
            self.bot.current_activity_index = 0
            
        self.rotate_activity_task.start()
        self.check_reminders.start()
        self.bot.loop.create_task(self.reminder_loop())
        # Interview reminder loop handled by check_reminders directly

    def cog_unload(self):
        self.rotate_activity_task.cancel()
        self.check_reminders.cancel()

    @tasks.loop(seconds=15)
    async def rotate_activity_task(self):
        """Background task to rotate bot activity texts"""
        try:
            try:
                local_r = redis.Redis(host=REDIS_HOST, port=6379, db=0, decode_responses=True)
            except:
                return # No redis connection

            texts_json = local_r.get('bot_activity_texts')
            interval_str = local_r.get('bot_activity_interval')
            activity_type = local_r.get('bot_activity_type') or 'playing'
            
            interval = int(interval_str) if interval_str and interval_str.isdigit() else 15
            
            if interval >= 5 and self.rotate_activity_task.seconds != interval:
                self.rotate_activity_task.change_interval(seconds=interval)

            texts = json.loads(texts_json) if texts_json else []
            texts = [t for t in texts if t.strip()]

            if not texts:
                texts = ["ARAM | S.T.O.R.M."]

            if len(texts) == 1:
                current_text = texts[0]
                if getattr(self.bot, 'activity_texts_cache', []) == texts and getattr(self.bot, 'activity_type_cache', 'playing') == activity_type:
                    return
            else:
                self.bot.current_activity_index = (self.bot.current_activity_index + 1) % len(texts)
                current_text = texts[self.bot.current_activity_index]

            activity_map = {
                'playing': discord.ActivityType.playing,
                'watching': discord.ActivityType.watching,
                'listening': discord.ActivityType.listening,
                'competing': discord.ActivityType.competing
            }
            
            act_type = activity_map.get(activity_type, discord.ActivityType.playing)
            self.bot.activity_texts_cache = texts
            self.bot.activity_type_cache = activity_type
            
            activity = discord.Activity(type=act_type, name=current_text)
            
            current_status = discord.Status.online
            # Default logic unchanged
            
            # Failsafe: if offline, force online
            if current_status == discord.Status.offline:
                current_status = discord.Status.online
            
            print(f"🔄 Setting activity to: {current_text}", flush=True)
            await self.bot.change_presence(status=current_status, activity=activity)

        except Exception as e:
             print(f"[Activity Rotation Error] {e}", flush=True)

    @rotate_activity_task.before_loop
    async def before_rotate(self):
        await self.bot.wait_until_ready()

    @tasks.loop(minutes=1)
    async def check_reminders(self):
        try:
            async with aiohttp.ClientSession() as session:
                async with session.get('http://web/api/internal_interviews.php?action=get_accepted_interviews') as resp:
                    if resp.status == 200:
                        data = await resp.json()
                        if data.get('success'):
                            interviews = data.get('data', [])
                            now = datetime.now(BANGKOK_TZ).replace(tzinfo=None)
                            
                            for interview in interviews:
                                try:
                                    it_time = None
                                    for fmt in ['%Y-%m-%d %H:%M', '%Y-%m-%d %H:%M:%S', '%d/%m/%Y %H:%M', '%d-%m-%Y %H:%M']:
                                        try:
                                            it_time = datetime.strptime(interview['interview_time_str'], fmt)
                                            break
                                        except ValueError:
                                            continue
                                            
                                    if not it_time:
                                        continue
                                    
                                    diff = (it_time - now).total_seconds() / 60
                                    response_id = interview['id']
                                    discord_id_or_name = interview['discord_id']
                                    
                                    # REMINDER 1: 15 Minutes Before
                                    if 10 <= diff <= 20:
                                        cache_key = f"reminder:15min:{response_id}"
                                        if not r.get(cache_key):
                                            await self.send_interview_dm_reminder(discord_id_or_name, interview['form_title'], it_time, 15)
                                            r.setex(cache_key, 3600, "sent")
                                            print(f"Sent 15min reminder for #{response_id}", flush=True)

                                    # REMINDER 2: At Time
                                    if 0 <= diff <= 5:
                                        cache_key = f"reminder:now:{response_id}"
                                        if not r.get(cache_key):
                                            channel_id_str = interview.get('webhook_url_welcome', '')
                                            await self.send_interview_channel_reminder(discord_id_or_name, interview['form_title'], channel_id_str, it_time)
                                            r.setex(cache_key, 3600, "sent")
                                            print(f"Sent channel reminder for #{response_id}", flush=True)
                                except Exception as e:
                                    print(f"Error processing reminder {interview.get('id')}: {e}")
        except Exception as e:
            print(f"Reminder Loop Error: {e}")

    @check_reminders.before_loop
    async def before_reminders(self):
        await self.bot.wait_until_ready()

    async def reminder_loop(self):
        """Background task to check and send event reminders"""
        await self.bot.wait_until_ready()
        print("🔔 Event specific Reminder Loop Started", flush=True)
        while not self.bot.is_closed():
            try:
                pending = DatabaseService.get_pending_reminders()
                for event in pending:
                    await self.send_event_reminder(event)
            except Exception as e:
                print(f"Reminder Loop Error: {e}", flush=True)
            await asyncio.sleep(60)

    async def send_event_reminder(self, event):
        """Send reminder to accepted users via thread and DM"""
        try:
            event_id = event.get('event_id')
            title = event.get('title', 'Event')
            reminder_min = event.get('trigger_reminder_min', 0)
            accepted_users = event.get('accepted_users', [])
            channel_id = event.get('channel_id')
            message_id = event.get('message_id')
            
            mentions = [f"<@{user.get('user_id')}>" for user in accepted_users if user.get('user_id')]
            mention_str = " ".join(mentions) if mentions else "No one has accepted yet"
            
            if channel_id:
                channel = self.bot.get_channel(int(channel_id))
                if channel:
                    reminder_msg = f"⏰ **Reminder!** Event **{title}** starts in {reminder_min} minutes!\n{mention_str}"
                    if message_id:
                        try:
                            original_msg = await channel.fetch_message(int(message_id))
                            await original_msg.reply(reminder_msg)
                        except:
                            await channel.send(reminder_msg)
                    else:
                        await channel.send(reminder_msg)
            
            for user in accepted_users:
                user_id = user.get('user_id')
                if user_id:
                    try:
                        discord_user = await self.bot.fetch_user(int(user_id))
                        if discord_user:
                            dm_msg = f"⏰ **Reminder!** Event **{title}** starts in {reminder_min} minutes!"
                            await discord_user.send(dm_msg)
                    except Exception as e:
                        print(f"DM Error: {e}")
                        
            DatabaseService.mark_reminder_sent(event_id, reminder_min)
            print(f"✅ Reminder sent for {title} ({reminder_min}min)", flush=True)
        except Exception as e:
            print(f"Send Reminder Error: {e}", flush=True)

    async def send_interview_channel_reminder(self, discord_id, form_title, channel_id_str, it_time):
        channel = None
        if str(channel_id_str).isdigit():
            channel = self.bot.get_channel(int(channel_id_str))
        elif '/webhooks/' in str(channel_id_str):
            match = re.search(r'/webhooks/(\d+)', str(channel_id_str))
            if match:
                channel = self.bot.get_channel(int(match.group(1)))
        
        if channel:
            mention = f"<@{discord_id}>" if str(discord_id).isdigit() else discord_id
            embed = discord.Embed(
                title="📢 ไกล้ถึงเวลาการสัมภาษณ์!",
                description=f"ไกล้ถึงเวลาการสัมภาษณ์สำหรับ {mention} (**{form_title}**) ให้มารอที่ห้อง https://discord.com/channels/1378791015825150122/1413141187346432211 ได้เลย",
                color=discord.Color.green()
            )
            embed.add_field(name="⏰ เวลา", value=f"<t:{int(it_time.replace(tzinfo=BANGKOK_TZ).timestamp())}:F>", inline=False)
            embed.set_footer(text="STORM Application System")
            await channel.send(content=mention, embed=embed)

    async def send_interview_dm_reminder(self, discord_id, form_title, it_time, minutes_before):
        user = None
        if str(discord_id).isdigit():
            try:
                user = await self.bot.fetch_user(int(discord_id))
            except:
                user = self.bot.get_user(int(discord_id))
                
        if user:
            try:
                embed = discord.Embed(
                    title="🔔 การแจ้งเตือนสัมภาษณ์ (Interview Reminder)",
                    description=f"การสัมภาษณ์ของคุณสำหรับ **{form_title}** จะเริ่มในอีก **{minutes_before} นาที**!",
                    color=discord.Color.gold()
                )
                embed.add_field(name="Time", value=f"<t:{int(it_time.replace(tzinfo=BANGKOK_TZ).timestamp())}:F>")
                await user.send(embed=embed)
            except Exception as e:
                print(f"DM failed: {e}")

async def setup(bot: commands.Bot):
    await bot.add_cog(BackgroundTasks(bot))
