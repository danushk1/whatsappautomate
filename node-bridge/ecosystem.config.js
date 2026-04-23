module.exports = {
  apps: [{
    name: 'genify-bridge',
    script: 'server.js',
    instances: 1,
    autorestart: true,
    watch: false,
    max_memory_restart: '1G',
    env: {
      NODE_ENV: 'production',
      PORT: 3000,
      LARAVEL_URL: 'http://172.235.19.62',
      LARAVEL_API_KEY: 'genify-node-bridge-secret-2026',
      QR_CODE_DIR: './public/qr-codes',
      SESSION_DIR: './sessions',
      LOG_LEVEL: 'info'
    },
    env_production: {
      NODE_ENV: 'production'
    }
  }]
};
