# Fly.io Deployment for S.T.O.R.M Discord Bot

## Prerequisites
1. Install Fly CLI: https://fly.io/docs/hands-on/install-flyctl/
2. Login: `flyctl auth login`

## Deploy Steps

### 1. Navigate to bot folder
```bash
cd bot
```

### 2. Create Fly app (first time only)
```bash
flyctl launch --name storm-discord-bot --region sin --no-deploy
```

### 3. Create PostgreSQL database
```bash
flyctl postgres create --name storm-db --region sin
flyctl postgres attach storm-db
```

### 4. Create Redis (Upstash)
```bash
flyctl redis create --name storm-redis --region sin
```

### 5. Set Discord Bot Token
```bash
flyctl secrets set DISCORD_BOT_TOKEN=your-token-here
```

### 6. Deploy
```bash
flyctl deploy
```

### 7. Get your app URL
```bash
flyctl info
```
URL will be: `https://storm-discord-bot.fly.dev`

## Update InfinityFree
Update `src/includes/bot_api.php`:
```php
private $botApiUrl = 'https://storm-discord-bot.fly.dev';
```

## Useful Commands
- View logs: `flyctl logs`
- Check status: `flyctl status`
- SSH into app: `flyctl ssh console`
- Restart: `flyctl apps restart`
