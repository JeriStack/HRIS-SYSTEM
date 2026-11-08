/**
 * BrowserSync Configuration for NEXORA HR
 * 
 * Configures proxying to localhost:8080 with polling-based file watching
 * to prevent Windows filesystem lock contention (UNKNOWN: scandir errors).
 */
module.exports = {
  proxy: 'localhost:8080',
  files: [
    'app/views/**/*.php',
    'public/assets/**/*.css',
    'public/assets/**/*.js'
  ],
  ignore: [
    'node_modules/**',
    'storage/**',
    'vendor/**',
    '.git/**'
  ],
  watchOptions: {
    usePolling: true,
    interval: 300
  },
  reloadDebounce: 500,
  reloadDelay: 200,
  notify: false,
  open: false
};

