import discord

class DiscordService:
    @staticmethod
    async def send_message(channel, content=None, embed=None, view=None):
        if channel:
            await channel.send(content=content, embed=embed, view=view)
