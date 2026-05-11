import psycopg2
import os
from psycopg2.extras import RealDictCursor

class DatabaseService:
    @staticmethod
    def get_connection():
        return psycopg2.connect(
            host=os.getenv('PGHOST', 'postgres'),
            database=os.getenv('PGDATABASE', 'botdb'),
            user=os.getenv('PGUSER', 'botuser'),
            password=os.getenv('PGPASSWORD', 'botpass'),
            port=os.getenv('PGPORT', '5432')
        )

    @staticmethod
    def save_event(data):
        conn = DatabaseService.get_connection()
        cur = conn.cursor()
        try:
            # Schema includes reminder fields now
            cur.execute(
                """
                INSERT INTO events (event_id, title, category, story, start_time, duration_minutes, timezone, platform, location_detail, status, reminder_minutes, ping_role_id)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                ON CONFLICT (event_id) DO UPDATE SET
                    title = EXCLUDED.title,
                    category = EXCLUDED.category,
                    story = EXCLUDED.story,
                    start_time = EXCLUDED.start_time,
                    duration_minutes = EXCLUDED.duration_minutes,
                    timezone = EXCLUDED.timezone,
                    location_detail = EXCLUDED.location_detail,
                    status = EXCLUDED.status,
                    reminder_minutes = EXCLUDED.reminder_minutes,
                    ping_role_id = EXCLUDED.ping_role_id
                """,
                (
                    data.get('event_id'),
                    data.get('title'),
                    data.get('category'),
                    data.get('story'),
                    data.get('start_time'),
                    data.get('duration_minutes'),
                    data.get('timezone'),
                    data.get('location', {}).get('platform'),
                    data.get('location', {}).get('detail'),
                    data.get('status', 'scheduled'),
                    data.get('reminder_minutes'),  # e.g., "15,30"
                    data.get('ping_role_id')
                )
            )
            conn.commit()
        finally:
            cur.close()
            conn.close()

    @staticmethod
    def ensure_schema():
        conn = DatabaseService.get_connection()
        conn.autocommit = True # Use autocommit for schema changes to avoid transaction blocks
        cur = conn.cursor()
        try:
            print("Migrating Schema: Attempting to add username column...", flush=True)
            # Blindly attempt to add the column. Postgres 9.6+ supports IF NOT EXISTS
            cur.execute("ALTER TABLE rsvp_responses ADD COLUMN IF NOT EXISTS username VARCHAR(255);")
            print("Schema Migration: Success (or column already existed).", flush=True)
        except Exception as e:
            print(f"Schema Migration Error: {e}", flush=True)
        finally:
            cur.close()
            conn.close()

    @staticmethod
    def save_rsvp(event_id, user_id, choice, username):
        conn = DatabaseService.get_connection()
        cur = conn.cursor()
        try:
            cur.execute(
                """
                INSERT INTO rsvp_responses (event_id, user_id, choice, username)
                VALUES (%s, %s, %s, %s)
                ON CONFLICT (event_id, user_id) DO UPDATE SET
                    choice = EXCLUDED.choice,
                    username = EXCLUDED.username,
                    submitted_at = CURRENT_TIMESTAMP
                """,
                (event_id, str(user_id), choice, username)
            )
            conn.commit()
        finally:
            cur.close()
            conn.close()

    @staticmethod
    def get_rsvps(event_id):
        conn = DatabaseService.get_connection()
        cur = conn.cursor(cursor_factory=RealDictCursor)
        try:
            cur.execute("SELECT user_id, choice, username FROM rsvp_responses WHERE event_id = %s", (event_id,))
            return cur.fetchall()
        finally:
            cur.close()
            conn.close()

    @staticmethod
    def get_active_events():
        conn = DatabaseService.get_connection()
        cur = conn.cursor(cursor_factory=RealDictCursor)
        try:
            # Return all events that are NOT cancelled, ordered by start_time
            query = """
                SELECT * FROM events 
                WHERE status != 'cancelled' 
                ORDER BY start_time ASC
            """
            cur.execute(query)
            events = cur.fetchall()
            
            # Attach RSVPs to each event
            for event in events:
                cur.execute("SELECT user_id, choice, username FROM rsvp_responses WHERE event_id = %s", (event['event_id'],))
                event['rsvps'] = cur.fetchall()
                
            return events
        finally:
            cur.close()
            conn.close()

    @staticmethod
    def cancel_event(event_id):
        conn = DatabaseService.get_connection()
        cur = conn.cursor()
        try:
            query = "UPDATE events SET status = 'cancelled' WHERE event_id = %s"
            cur.execute(query, (event_id,))
            conn.commit()
            return cur.rowcount > 0
        finally:
            cur.close()
            conn.close()

    @staticmethod
    def delete_event(event_id):
        """Permanently delete an event and its RSVPs from database"""
        conn = DatabaseService.get_connection()
        cur = conn.cursor()
        try:
            # Delete RSVPs first (foreign key reference)
            cur.execute("DELETE FROM rsvp_responses WHERE event_id = %s", (event_id,))
            # Delete reminders
            cur.execute("DELETE FROM event_reminders WHERE event_id = %s", (event_id,))
            # Delete the event
            cur.execute("DELETE FROM events WHERE event_id = %s", (event_id,))
            conn.commit()
            return cur.rowcount > 0
        finally:
            cur.close()
            conn.close()

    @staticmethod
    def ensure_reminder_schema():
        """Add reminder-related columns and tables"""
        conn = DatabaseService.get_connection()
        conn.autocommit = True
        cur = conn.cursor()
        try:
            print("Migrating Schema: Adding reminder columns...", flush=True)
            # Add new columns to events table
            cur.execute("ALTER TABLE events ADD COLUMN IF NOT EXISTS reminder_minutes VARCHAR(50);")
            cur.execute("ALTER TABLE events ADD COLUMN IF NOT EXISTS ping_role_id VARCHAR(50);")
            cur.execute("ALTER TABLE events ADD COLUMN IF NOT EXISTS channel_id VARCHAR(50);")
            cur.execute("ALTER TABLE events ADD COLUMN IF NOT EXISTS message_id VARCHAR(50);")
            
            # Create event_reminders table
            cur.execute("""
                CREATE TABLE IF NOT EXISTS event_reminders (
                    id SERIAL PRIMARY KEY,
                    event_id VARCHAR(255) NOT NULL,
                    reminder_minutes INTEGER NOT NULL,
                    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(event_id, reminder_minutes)
                );
            """)
            print("Schema Migration: Reminder schema ready.", flush=True)
        except Exception as e:
            print(f"Reminder Schema Error: {e}", flush=True)
        finally:
            cur.close()
            conn.close()

    @staticmethod
    def get_pending_reminders():
        """Get events that need reminders sent"""
        conn = DatabaseService.get_connection()
        cur = conn.cursor(cursor_factory=RealDictCursor)
        try:
            # Get events with reminders that haven't been sent yet
            query = """
                SELECT e.*, 
                       EXTRACT(EPOCH FROM (e.start_time - NOW())) / 60 AS minutes_until
                FROM events e
                WHERE e.status = 'scheduled'
                  AND e.reminder_minutes IS NOT NULL
                  AND e.start_time > NOW()
                ORDER BY e.start_time ASC
            """
            cur.execute(query)
            events = cur.fetchall()
            
            pending = []
            for event in events:
                if not event.get('reminder_minutes'):
                    continue
                    
                minutes_until = event.get('minutes_until', 9999)
                reminder_list = [int(x.strip()) for x in event['reminder_minutes'].split(',') if x.strip()]
                
                for reminder_min in reminder_list:
                    # Check if this reminder should trigger and hasn't been sent
                    if minutes_until <= reminder_min:
                        # Check if already sent
                        cur.execute(
                            "SELECT 1 FROM event_reminders WHERE event_id = %s AND reminder_minutes = %s",
                            (event['event_id'], reminder_min)
                        )
                        if not cur.fetchone():
                            # Get RSVPs
                            cur.execute(
                                "SELECT user_id, username FROM rsvp_responses WHERE event_id = %s AND choice = 'going'",
                                (event['event_id'],)
                            )
                            event['accepted_users'] = cur.fetchall()
                            event['trigger_reminder_min'] = reminder_min
                            pending.append(event)
                            break  # Only one reminder at a time per event
            
            return pending
        finally:
            cur.close()
            conn.close()

    @staticmethod
    def mark_reminder_sent(event_id, reminder_minutes):
        """Mark a reminder as sent"""
        conn = DatabaseService.get_connection()
        cur = conn.cursor()
        try:
            cur.execute(
                "INSERT INTO event_reminders (event_id, reminder_minutes) VALUES (%s, %s) ON CONFLICT DO NOTHING",
                (event_id, reminder_minutes)
            )
            conn.commit()
        finally:
            cur.close()
            conn.close()

    @staticmethod
    def update_event_message_id(event_id, channel_id, message_id):
        """Store the Discord message ID for an event"""
        conn = DatabaseService.get_connection()
        cur = conn.cursor()
        try:
            cur.execute(
                "UPDATE events SET channel_id = %s, message_id = %s WHERE event_id = %s",
                (str(channel_id), str(message_id), event_id)
            )
            conn.commit()
        finally:
            cur.close()
            conn.close()

    # ========== ROLE PANELS ==========

    @staticmethod
    def ensure_role_panels_schema():
        """Create role_panels table if not exists"""
        conn = DatabaseService.get_connection()
        conn.autocommit = True
        cur = conn.cursor()
        try:
            cur.execute("""
                CREATE TABLE IF NOT EXISTS role_panels (
                    id SERIAL PRIMARY KEY,
                    panel_id VARCHAR(100) UNIQUE NOT NULL,
                    title VARCHAR(255) NOT NULL DEFAULT 'Role Selection',
                    description TEXT DEFAULT '',
                    embed_color VARCHAR(10) DEFAULT '#5865F2',
                    channel_id VARCHAR(50),
                    message_id VARCHAR(50),
                    guild_id VARCHAR(50),
                    buttons JSONB DEFAULT '[]',
                    mode VARCHAR(20) DEFAULT 'toggle',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                );
            """)
            print("Schema Migration: role_panels table ready.", flush=True)
        except Exception as e:
            print(f"Role Panels Schema Error: {e}", flush=True)
        finally:
            cur.close()
            conn.close()

    @staticmethod
    def get_role_panels():
        """Get all role panels"""
        conn = DatabaseService.get_connection()
        cur = conn.cursor(cursor_factory=RealDictCursor)
        try:
            cur.execute("SELECT * FROM role_panels ORDER BY created_at DESC")
            return cur.fetchall()
        finally:
            cur.close()
            conn.close()

    @staticmethod
    def get_role_panel(panel_id):
        """Get a single role panel by panel_id"""
        conn = DatabaseService.get_connection()
        cur = conn.cursor(cursor_factory=RealDictCursor)
        try:
            cur.execute("SELECT * FROM role_panels WHERE panel_id = %s", (panel_id,))
            return cur.fetchone()
        finally:
            cur.close()
            conn.close()

    @staticmethod
    def save_role_panel(data):
        """Create or update a role panel"""
        conn = DatabaseService.get_connection()
        cur = conn.cursor()
        try:
            import json as _json
            buttons_json = _json.dumps(data.get('buttons', []))
            cur.execute("""
                INSERT INTO role_panels (panel_id, title, description, embed_color, channel_id, message_id, guild_id, buttons, mode)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s)
                ON CONFLICT (panel_id) DO UPDATE SET
                    title = EXCLUDED.title,
                    description = EXCLUDED.description,
                    embed_color = EXCLUDED.embed_color,
                    channel_id = EXCLUDED.channel_id,
                    message_id = EXCLUDED.message_id,
                    guild_id = EXCLUDED.guild_id,
                    buttons = EXCLUDED.buttons,
                    mode = EXCLUDED.mode,
                    updated_at = CURRENT_TIMESTAMP
            """, (
                data.get('panel_id'),
                data.get('title', 'Role Selection'),
                data.get('description', ''),
                data.get('embed_color', '#5865F2'),
                data.get('channel_id'),
                data.get('message_id'),
                data.get('guild_id'),
                buttons_json,
                data.get('mode', 'toggle')
            ))
            conn.commit()
            return True
        except Exception as e:
            print(f"Save Role Panel Error: {e}", flush=True)
            return False
        finally:
            cur.close()
            conn.close()

    @staticmethod
    def delete_role_panel(panel_id):
        """Delete a role panel"""
        conn = DatabaseService.get_connection()
        cur = conn.cursor()
        try:
            cur.execute("DELETE FROM role_panels WHERE panel_id = %s", (panel_id,))
            conn.commit()
            return cur.rowcount > 0
        finally:
            cur.close()
            conn.close()

    @staticmethod
    def update_role_panel_message(panel_id, channel_id, message_id):
        """Update the message_id after sending to Discord"""
        conn = DatabaseService.get_connection()
        cur = conn.cursor()
        try:
            cur.execute(
                "UPDATE role_panels SET channel_id = %s, message_id = %s, updated_at = CURRENT_TIMESTAMP WHERE panel_id = %s",
                (str(channel_id), str(message_id), panel_id)
            )
            conn.commit()
        finally:
            cur.close()
            conn.close()

    @staticmethod
    def update_event_thread_id(event_id, thread_id):
        """Store the Discord thread ID for an event"""
        conn = DatabaseService.get_connection()
        cur = conn.cursor()
        try:
            # First ensure thread_id column exists
            cur.execute("ALTER TABLE events ADD COLUMN IF NOT EXISTS thread_id VARCHAR(50);")
            cur.execute(
                "UPDATE events SET thread_id = %s WHERE event_id = %s",
                (str(thread_id), event_id)
            )
            conn.commit()
        finally:
            cur.close()
            conn.close()
