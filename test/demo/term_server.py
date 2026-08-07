#!/usr/bin/env python3
# SPDX-FileCopyrightText: 2026 Mirian Brechtel <brechtel@med.uni-frankfurt.de>
# SPDX-License-Identifier: AGPL-3.0-or-later
#
# Minimal xterm.js web terminal: aiohttp serves the page (xterm.js from the
# Debian libjs-xterm package) and bridges a WebSocket to a bash PTY.
# Replaces ttyd, which is not packaged in Debian trixie.

import asyncio
import fcntl
import os
import pty
import struct
import termios

from aiohttp import web, WSMsgType

COLS, ROWS = 136, 36

PAGE = f"""<!doctype html>
<html><head><meta charset="utf-8">
<link rel="stylesheet" href="/xterm/xterm.css">
<script src="/xterm/xterm.js"></script>
<style>
  body {{ margin:0; background:#1e1e2e; height:100vh; display:flex;
         align-items:center; justify-content:center; }}
</style></head>
<body><div id="term"></div>
<script>
  const term = new Terminal({{
    cols: {COLS}, rows: {ROWS}, fontSize: 20, cursorBlink: true,
    fontFamily: 'monospace',
    theme: {{ background: '#1e1e2e', foreground: '#cdd6f4' }},
  }});
  term.open(document.getElementById('term'));
  const ws = new WebSocket('ws://' + location.host + '/ws');
  ws.binaryType = 'arraybuffer';
  ws.onmessage = (e) => term.write(new Uint8Array(e.data));
  term.onData((d) => ws.send(d));
  window.addEventListener('click', () => term.focus());
  term.focus();
</script></body></html>"""


async def index(_request):
    return web.Response(text=PAGE, content_type='text/html')


async def ws_handler(request):
    ws = web.WebSocketResponse()
    await ws.prepare(request)

    pid, fd = pty.fork()
    if pid == 0:
        os.environ['TERM'] = 'xterm-256color'
        os.environ['PS1'] = r'\[\e[1;34m\]demo\[\e[0m\]:\[\e[1;36m\]\w\[\e[0m\]$ '
        os.chdir('/work')
        os.execvp('bash', ['bash', '--norc', '-i'])

    winsize = struct.pack('HHHH', ROWS, COLS, 0, 0)
    fcntl.ioctl(fd, termios.TIOCSWINSZ, winsize)

    loop = asyncio.get_running_loop()

    def on_pty_output():
        try:
            data = os.read(fd, 65536)
        except OSError:
            loop.remove_reader(fd)
            asyncio.ensure_future(ws.close())
            return
        asyncio.ensure_future(ws.send_bytes(data))

    loop.add_reader(fd, on_pty_output)
    try:
        async for msg in ws:
            if msg.type == WSMsgType.TEXT:
                os.write(fd, msg.data.encode())
            elif msg.type == WSMsgType.BINARY:
                os.write(fd, msg.data)
    finally:
        loop.remove_reader(fd)
        try:
            os.kill(pid, 15)
        except ProcessLookupError:
            pass
    return ws


app = web.Application()
app.router.add_get('/', index)
app.router.add_get('/ws', ws_handler)
app.router.add_static('/xterm/', '/usr/share/javascript/xterm/')

if __name__ == '__main__':
    web.run_app(app, host='127.0.0.1', port=7681, print=None)
