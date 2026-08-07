#!/usr/bin/env python3
# SPDX-FileCopyrightText: 2026 Mirian Brechtel <brechtel@med.uni-frankfurt.de>
# SPDX-License-Identifier: AGPL-3.0-or-later
#
# The screenplay: drives Chromium (Files app) and a ttyd/xterm.js terminal
# against a real Nextcloud Repositories instance while the screen is recorded.

import os
import sys
import time
import traceback
from urllib.parse import quote

from selenium import webdriver
from selenium.webdriver.chromium.service import ChromiumService
from selenium.webdriver.common.by import By
from selenium.webdriver.common.keys import Keys
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC

TARGET = os.environ['TARGET_URL'].rstrip('/')
USER = os.environ.get('DEMO_USER', 'admin')
PASS = os.environ['DEMO_PASS']
# the credential typed on camera; defaults to the web password for dev targets
APP_PASS = os.environ.get('DEMO_APP_PASS', PASS)
REPO = os.environ.get('REPO', 'demo')
TERMINAL = 'http://127.0.0.1:7681'

def log(msg):
    print(f'[demo] {msg}', flush=True)

def make_driver():
    options = webdriver.ChromeOptions()
    options.binary_location = '/usr/bin/chromium'
    options.add_argument('--no-sandbox')
    options.add_argument('--disable-dev-shm-usage')
    options.add_argument('--disable-gpu')
    options.add_argument('--window-size=1920,1080')
    options.add_argument('--window-position=0,0')
    options.add_argument('--kiosk')
    options.add_argument('--hide-scrollbars')
    options.add_argument('--force-device-scale-factor=1')
    # no "Chrome is being controlled…" infobar; the title cards carry the
    # automated-demo disclosure instead
    options.add_experimental_option('excludeSwitches', ['enable-automation'])
    options.add_experimental_option('useAutomationExtension', False)
    # no save-password popup over the login scene
    options.add_argument('--password-store=basic')
    options.add_experimental_option('prefs', {
        'credentials_enable_service': False,
        'profile.password_manager_enabled': False,
    })
    service = ChromiumService(executable_path='/usr/bin/chromedriver')
    return webdriver.Chrome(options=options, service=service)

def title_card(driver, title, subtitle='', seconds=3.5):
    if MAIN_TAB is not None and driver.current_window_handle != MAIN_TAB:
        driver.switch_to.window(MAIN_TAB)
    html = f'''<!doctype html><html><head><meta charset="utf-8"><style>
      * {{ box-sizing:border-box; }}
      body {{ margin:0; height:100vh; display:flex; flex-direction:column;
             align-items:center; justify-content:center; text-align:center;
             background:
               radial-gradient(1200px 600px at 80% -10%, rgba(59,130,246,.35), transparent 60%),
               radial-gradient(900px 500px at 10% 110%, rgba(14,165,233,.25), transparent 60%),
               linear-gradient(150deg,#0b1220 0%,#0f2249 55%,#123a7a 100%);
             color:#f8fafc; font-family:'Liberation Sans',sans-serif; }}
      .badge {{ position:absolute; top:44px; left:50%; transform:translateX(-50%);
             font-size:18px; letter-spacing:.3em; text-transform:uppercase;
             color:#7dd3fc; border:1px solid rgba(125,211,252,.45);
             border-radius:999px; padding:10px 30px 10px 34px;
             background:rgba(8,20,45,.5); }}
      .badge::before {{ content:'●'; color:#f87171; margin-right:14px; }}
      h1 {{ font-size:68px; margin:0 0 10px; max-width:76vw; font-weight:700;
             letter-spacing:-0.01em; }}
      .rule {{ width:120px; height:4px; border-radius:2px; margin:18px 0 26px;
             background:linear-gradient(90deg,#38bdf8,#818cf8); }}
      p  {{ font-size:30px; color:#a5c8f5; margin:0; max-width:62vw; line-height:1.45; }}
      .foot {{ position:absolute; bottom:40px; left:50%; transform:translateX(-50%);
             font-size:19px; color:#5b7db1; }}
      .foot b {{ color:#8fb4e8; font-weight:600; }}
    </style></head><body>
      <div class="badge">Demo</div>
      <h1>{title}</h1><div class="rule"></div><p>{subtitle}</p>
      <div class="foot"><b>Nextcloud Repositories</b> &nbsp;&middot;&nbsp; scripted with Selenium, played against a live instance</div>
    </body></html>'''
    driver.get('data:text/html;charset=utf-8,' + quote(html))
    time.sleep(seconds)

def type_line(driver, text, wait_after=2.0, char_delay=0.045):
    element = driver.switch_to.active_element
    for ch in text:
        element.send_keys(ch)
        time.sleep(char_delay)
    time.sleep(0.4)
    element.send_keys(Keys.ENTER)
    time.sleep(wait_after)

MAIN_TAB = None
TERM_TAB = None

def open_terminal(driver):
    """Switch to the persistent terminal tab (one PTY for the whole demo)."""
    global TERM_TAB
    if TERM_TAB is None:
        driver.switch_to.new_window('tab')
        TERM_TAB = driver.current_window_handle
        driver.get(TERMINAL)
        WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.TAG_NAME, 'body')))
        time.sleep(1.5)
    else:
        driver.switch_to.window(TERM_TAB)
        time.sleep(0.8)
    driver.find_element(By.TAG_NAME, 'body').click()
    time.sleep(0.5)

def main_tab(driver):
    driver.switch_to.window(MAIN_TAB)
    time.sleep(0.5)

def nextcloud_login(driver):
    driver.get(f'{TARGET}/login')
    WebDriverWait(driver, 20).until(EC.presence_of_element_located((By.ID, 'user')))
    time.sleep(1)
    driver.find_element(By.ID, 'user').send_keys(USER)
    time.sleep(0.5)
    driver.find_element(By.ID, 'password').send_keys(PASS)
    time.sleep(0.5)
    driver.find_element(By.CSS_SELECTOR, 'button[type="submit"]').click()
    WebDriverWait(driver, 30).until(lambda d: '/login' not in d.current_url)
    time.sleep(2)

def open_repo_folder(driver, linger=5.0):
    if MAIN_TAB is not None and driver.current_window_handle != MAIN_TAB:
        driver.switch_to.window(MAIN_TAB)
        time.sleep(0.5)
    driver.get(f'{TARGET}/index.php/apps/files/?dir=/{REPO}')
    WebDriverWait(driver, 30).until(
        EC.presence_of_element_located((By.CSS_SELECTOR, '[data-cy-files-list-row-name]')))
    time.sleep(linger)

def soft(fn, name):
    try:
        fn()
        log(f'scene ok: {name}')
    except Exception:
        log(f'scene SKIPPED ({name}):')
        traceback.print_exc()

def main():
    global MAIN_TAB
    driver = make_driver()
    MAIN_TAB = driver.current_window_handle
    clone_url = f'{TARGET}/apps/repos/{REPO}.git'

    # --- opening ---
    title_card(driver, 'Nextcloud Repositories',
               'Git, Git-Annex and Datalad — inside your Nextcloud', 4)
    title_card(driver, 'One history, two views',
               'The Files app and git clones share the same repository', 3)

    # --- scene: the folder in the Files app ---
    log('logging in')
    nextcloud_login(driver)
    title_card(driver, 'A repository folder', 'It looks like any Nextcloud folder…', 3)
    open_repo_folder(driver, linger=6)

    # --- scene: clone in the terminal, authenticating once ---
    title_card(driver, '…but it is a git repository',
               'Clone it over HTTPS — authenticate once with an app password', 3)
    open_terminal(driver)
    type_line(driver, 'clear', 0.6)
    type_line(driver, f'git clone {clone_url}', 3)
    # git prompts Username / Password; the password is not echoed on screen
    type_line(driver, USER, 1.5)
    type_line(driver, APP_PASS, 10, char_delay=0.02)
    type_line(driver, f'cd {REPO} && ls -lh', 3)
    type_line(driver, 'cat README.md', 3.5)
    title_card(driver, 'Typed once, stored by git',
               'Every later fetch, push and annex transfer reuses that credential', 3)

    # --- scene: annexed data over the same URL ---
    main_tab(driver)
    title_card(driver, 'Big data stays lean',
               'Large files are git-annex pointers — fetch content on demand', 3)
    open_terminal(driver)
    type_line(driver, 'git annex init demo-client', 4)
    type_line(driver, 'ls -lh data.bin   # just a pointer so far', 3)
    type_line(driver, 'git annex get data.bin', 10)
    type_line(driver, 'ls -lh data.bin   # real content, same URL, same credential', 4)

    # --- scene: push and see it in the Files app ---
    main_tab(driver)
    title_card(driver, 'Push like any remote', '…and watch the Files app follow', 3)
    open_terminal(driver)
    type_line(driver, 'date > from-git.txt   # something new to push', 1.5)
    type_line(driver, 'git add from-git.txt && git commit -m "Add from-git.txt"', 3)
    type_line(driver, 'git push origin main', 6)
    open_repo_folder(driver, linger=6)

    # --- scene: file history in the sidebar (best effort) ---
    # show the file we just pushed: its fresh, attributed commit is the point
    def history_scene():
        driver.execute_script(
            f"OCA.Files.Sidebar.open('/{REPO}/from-git.txt')")
        time.sleep(3)
        tab = driver.find_element(
            By.XPATH, "//*[self::a or self::button][contains(., 'History')]")
        tab.click()
        time.sleep(7)
    soft(history_scene, 'history sidebar')

    # --- scene: browser edits become commits (best effort) ---
    def edit_scene():
        title_card(driver, 'Browser edits become commits',
                   'Every save is attributed to the Nextcloud user', 3)
        open_repo_folder(driver, linger=1)
        row = driver.find_element(
            By.CSS_SELECTOR, f'[data-cy-files-list-row-name="notes.txt"] a')
        row.click()
        editor = WebDriverWait(driver, 20).until(
            EC.presence_of_element_located((By.CSS_SELECTOR, '[contenteditable="true"]')))
        time.sleep(2)
        editor.click()
        editor.send_keys(Keys.CONTROL, Keys.END)
        editor.send_keys(Keys.ENTER, 'Edited live in the browser during this demo.')
        time.sleep(4)  # autosave
        editor.send_keys(Keys.ESCAPE)
        time.sleep(2)
    soft(edit_scene, 'browser edit')

    def pull_scene():
        open_terminal(driver)
        type_line(driver, 'git pull origin main', 5)
        type_line(driver, 'git log --oneline --format="%an — %s" -3', 5)
    soft(pull_scene, 'pull browser commit')

    # --- closing ---
    title_card(driver, 'Nextcloud Repositories',
               'github.com/mkbrechtel/nextcloud-repos', 5)

    driver.quit()
    log('screenplay finished')

if __name__ == '__main__':
    main()
