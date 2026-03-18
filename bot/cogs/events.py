import discord
from discord.ext import commands
import aiohttp
import json
from datetime import datetime
from ui.components import RejectReasonModal, RescheduleModal

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

async def setup(bot: commands.Bot):
    await bot.add_cog(EventsCog(bot))
