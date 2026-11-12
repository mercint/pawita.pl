#!/usr/bin/env python3
"""Guarded Elementor carousel update via WordPress REST API."""
import base64, json, os, pathlib, sys, urllib.request, urllib.parse, urllib.error

BASE="https://pawita.pl/wp-json/wp/v2"
PAGE=1409
WIDGET="2c21f00"
IDS=list(range(1661,1673))
OUT=pathlib.Path("output")
OUT.mkdir(exist_ok=True)

def api(method,path,data=None):
    user=os.environ.get("WP_USERNAME","admin")
    pwd=os.environ["WP_APP_PASSWORD"]
    token=base64.b64encode((user+":"+pwd).encode()).decode()
    headers={"Authorization":"Basic "+token,"Accept":"application/json",
             "User-Agent":"Pawita-Gallery-Updater/1.0"}
    payload=None
    if data is not None:
        headers["Content-Type"]="application/json"
        payload=json.dumps(data,ensure_ascii=False).encode()
    req=urllib.request.Request(BASE+path,data=payload,headers=headers,method=method)
    with urllib.request.urlopen(req,timeout=40) as response:
        return json.load(response)

def locate(nodes):
    found=[]
    def visit(items):
        if isinstance(items,list):
            for item in items: visit(item)
        elif isinstance(items,dict):
            if items.get("id")==WIDGET: found.append(items)
            visit(items.get("elements",[]))
    visit(nodes)
    if len(found)!=1 or found[0].get("widgetType")!="image-carousel":
        raise ValueError("Carousel widget missing, duplicated or changed; no write")
    return found[0]

def main():
    page=api("GET",f"/pages/{PAGE}?context=edit")
    raw=page.get("meta",{}).get("_elementor_data")
    if page.get("status")!="publish" or not isinstance(raw,str) or not raw:
        raise ValueError("Page status or Elementor data unexpected")
    document=json.loads(raw)
    gallery=locate(document).get("settings",{}).get("carousel")
    if not isinstance(gallery,list) or any(
        not isinstance(item,dict) or "id" not in item or "url" not in item
        for item in gallery):
        raise ValueError("Carousel image structure unexpected")
    (OUT/"staffa-elementor-backup-before.json").write_text(raw,encoding="utf-8")
    existing={str(item["id"]) for item in gallery}
    additions=[]
    for media_id in IDS:
        media=api("GET",f"/media/{media_id}?context=edit")
        url=media.get("source_url","")
        if not str(media.get("date","")).startswith("2026-11-12") or not str(media.get("mime_type","")).startswith("image/"):
            raise ValueError(f"Image {media_id} mismatches date or type")
        if not url.startswith("https://pawita.pl/wp-content/uploads/"):
            raise ValueError(f"Image {media_id} has unexpected URL")
        if str(media_id) not in existing:
            additions.append({"id":media_id,"url":url})
    print(f"Original images: {len(gallery)}; adding: {len(additions)}")
    if not additions:
        print("All 12 images already included, no write performed")
        return
    gallery.extend(additions)
    proposed=json.dumps(document,ensure_ascii=False,separators=(",",":"))
    (OUT/"staffa-elementor-proposed.json").write_text(proposed,encoding="utf-8")
    fresh=api("GET",f"/pages/{PAGE}?context=edit")
    if fresh.get("meta",{}).get("_elementor_data")!=raw:
        raise ValueError("Page updated concurrently: aborting without changes")
    api("POST",f"/pages/{PAGE}",{"meta":{"_elementor_data":proposed}})
    verified=api("GET",f"/pages/{PAGE}?context=edit")
    saved=verified.get("meta",{}).get("_elementor_data")
    if not isinstance(saved,str):
        raise RuntimeError("WordPress did not return Elementor data after write")
    final_gallery=locate(json.loads(saved)).get("settings",{}).get("carousel",[])
    saved_ids={str(item.get("id")) for item in final_gallery if isinstance(item,dict)}
    missing=[item["id"] for item in additions if str(item["id"]) not in saved_ids]
    if missing:
        raise RuntimeError(f"Images not persisted: {missing}; backup available")
    print(f"SUCCESS: {len(additions)} images added, total {len(final_gallery)}")

if __name__=="__main__":
    try:
        main()
    except urllib.error.HTTPError as err:
        print(f"WordPress HTTP {err.code}; update was not verified",file=sys.stderr)
        sys.exit(1)
    except Exception as err:
        print(f"Update error: {type(err).__name__}: {err}",file=sys.stderr)
        sys.exit(1)
