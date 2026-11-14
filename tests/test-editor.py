import json, time
from pathlib import Path
from playwright.sync_api import sync_playwright
OUT=Path('/home/user/scratch-only/test-results'); OUT.mkdir(exist_ok=True)
URL='http://127.0.0.1:5173/'
res={}
with sync_playwright() as p:
    b=p.chromium.launch(headless=True,args=['--no-sandbox','--disable-dev-shm-usage'])
    c=b.new_context(viewport={'width':1440,'height':900},locale='ru-RU',accept_downloads=True)
    pg=c.new_page(); errs=[]; console=[]; failed=[]
    pg.on('pageerror',lambda e: errs.append(str(e)))
    pg.on('console',lambda m: console.append(m.text) if m.type in ('error','warning') else None)
    pg.on('requestfailed',lambda r: failed.append({'url':r.url,'error':r.failure}))
    t=time.monotonic()
    pg.goto(URL,wait_until='domcontentloaded',timeout=60000)
    try:
        pg.wait_for_selector('[class*="stage-header_stage-size-toggle-group"]',timeout=90000)
        res['ready_ms']=round((time.monotonic()-t)*1000); res['loaded']=True
    except Exception as e:
        res['loaded']=False; res['ready_error']=str(e)
    pg.wait_for_timeout(2500)
    pg.screenshot(path=str(OUT/'desktop.png'))
    res['gui_keys']=pg.evaluate('()=>window.GUI?Object.keys(window.GUI).slice(0,30):null')
    res['has_vm']=pg.evaluate('()=>!!(window.__scratchOnly&&window.__scratchOnly.vm)')
    res['body_text_tail']=pg.locator('body').inner_text()[-1200:]
    res['has_roblox']=pg.get_by_text('Roblox',exact=True).count()>0
    res['has_arduino']=pg.get_by_text('Arduino',exact=True).count()>0
    # download
    try:
        with pg.expect_download(timeout=20000) as dl:
            pg.click('#download-btn')
        d=dl.value; path=OUT/'download.sb3'; d.save_as(str(path))
        import zipfile
        with zipfile.ZipFile(path) as z:
            proj=json.loads(z.read('project.json'))
            res['download']={'name':d.suggested_filename,'bytes':path.stat().st_size,'targets':len(proj.get('targets',[])),'zip_error':z.testzip()}
    except Exception as e: res['download_error']=str(e)
    # autosave rename + reload
    try:
        title=pg.locator('input').first
        title.fill('Только Scratch тест')
        title.blur(); pg.wait_for_timeout(2300)
        pg.reload(wait_until='domcontentloaded')
        pg.wait_for_selector('[class*="stage-header_stage-size-toggle-group"]',timeout=90000)
        pg.wait_for_timeout(2500)
        res['autosave_restored_title']=pg.locator('input').first.input_value()
        res['status_after_reload']=pg.locator('#save-status').inner_text()
    except Exception as e: res['autosave_error']=str(e)
    res['page_errors']=errs; res['console']=console[:30]; res['failed_requests']=failed[:20]
    b.close()
(OUT/'results.json').write_text(json.dumps(res,ensure_ascii=False,indent=2))
print(json.dumps(res,ensure_ascii=False,indent=2))
