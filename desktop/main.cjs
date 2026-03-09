const { app, BrowserWindow } = require('electron');

function resolveTargetUrl() {
  const base = (process.env.APP_BASE_URL || 'http://127.0.0.1:8000').replace(/\/$/, '');
  const view = (process.env.DESKTOP_VIEW || 'compra').toLowerCase();

  if (view === 'validador') return `${base}/validador`;
  if (view === 'vendedor') return `${base}/vendedor`;
  return `${base}/compra`;
}

function createWindow() {
  const win = new BrowserWindow({
    width: 1280,
    height: 840,
    minWidth: 1024,
    minHeight: 700,
    autoHideMenuBar: true,
    title: 'ChárroTickets Desktop',
    webPreferences: {
      contextIsolation: true,
      nodeIntegration: false,
      sandbox: true,
    },
  });

  win.loadURL(resolveTargetUrl());
}

app.whenReady().then(() => {
  createWindow();

  app.on('activate', () => {
    if (BrowserWindow.getAllWindows().length === 0) createWindow();
  });
});

app.on('window-all-closed', () => {
  if (process.platform !== 'darwin') app.quit();
});
