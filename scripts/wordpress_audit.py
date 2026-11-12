#!/usr/bin/env python3
"""Read-only WordPress connection and gallery-media inventory."""
import base64
import datetime as dt
import json
import os
import sys
import urllib.error
import urllib.parse
import urllib.request
from zoneinfo import ZoneInfo

BASE = "https://pawita.pl/wp-json/wp/v2"
USER = os.environ.get("WP_USERNAME", "admin")
PASSWORD = os.environ["WP_APP_PASSWORD"]
AUTH = "Basic " + base64.b64encode(f"{USER}:{PASSWORD}".encode()).decode()
TODAY = dt.datetime.now(ZoneInfo("Europe/Warsaw")).date().isoformat()

def get(path, params=None):
    url = BASE + path
    if params:
        url += "?" + urllib.parse.urlencode(params)
    req = urllib.request.Request(url, headers={"Authorization": AUTH, "Accept": "application/json", "User-Agent": "Pawita-GitHub-WordPress-Integration/1.0"})
    with urllib.request.urlopen(req, timeout=25) as response:
        return json.load(response)

def main():
    user = get("/users/me")
    print("Polaczenie WordPress REST API: OK")
    print("Konto ID:", user["id"], "| nazwa:", user["slug"])
    page = get("/pages/1409", {"context": "edit"})
    print("Strona:", page["id"], "| status:", page["status"], "| tytul:", page.get("title", {}).get("rendered", ""))
    items = []
    for page_no in range(1, 21):
        try:
            batch = get("/media", {"per_page": 100, "page": page_no, "orderby": "date", "order": "desc", "context": "edit"})
        except urllib.error.HTTPError as e:
            if e.code == 400 and page_no > 1:
                break
            raise
        items.extend(batch)
        if len(batch) < 100:
            break
    compact = [
        {"id": m["id"], "date": m.get("date"), "title": m.get("title", {}).get("rendered", ""),
         "source_url": m.get("source_url"), "mime_type": m.get("mime_type")}
        for m in items
    ]
    today = [m for m in compact if (m.get("date") or "").startswith(TODAY) and (m.get("mime_type") or "").startswith("image/")]
    raw_elementor = page.get("meta", {}).get("_elementor_data")
    elementor_widgets = []
    try:
        tree = json.loads(raw_elementor) if isinstance(raw_elementor, str) else raw_elementor
        def walk(nodes):
            if isinstance(nodes, list):
                for node in nodes:
                    walk(node)
            elif isinstance(nodes, dict):
                if "elType" in nodes:
                    settings = nodes.get("settings", {}) or {}
                    image_keys = {k: v for k, v in settings.items()
                                  if "gallery" in k.lower() or "image" in k.lower()
                                  or k in ("ids", "wp_gallery")}
                    elementor_widgets.append({
                        "id": node.get("id"), "elType": node.get("elType"),
                        "widgetType": node.get("widgetType"),
                        "image_settings": image_keys if node.get("widgetType") in
                          ("gallery", "image-gallery", "basic-gallery", "media-carousel", "image-carousel") else {},
                        "setting_keys": list(settings.keys()) if node.get("widgetType") else []})
                walk(nodes.get("elements", []))
        walk(tree)
    except (ValueError, TypeError):
        pass
    result = {"checked_local_date": TODAY, "page_id": 1409, "page_status": page["status"],
              "media_checked": len(compact), "today_images": today,
              "recent_media": compact[:30],
              "elementor_meta_visible": "_elementor_data" in page.get("meta", {}),\n              "elementor_data_type": type(raw_elementor).__name__,\n              "elementor_data_length": len(raw_elementor) if raw_elementor else 0,\n              "elementor_widgets": elementor_widgets}
    os.makedirs("output", exist_ok=True)
    with open("output/wordpress-audit.json", "w", encoding="utf-8") as f:
        json.dump(result, f, ensure_ascii=False, indent=2)
    print("Sprawdzone media:", len(compact), "| nowe obrazy z dzisiaj:", len(today))
    for image in today:
        print("Obraz:", image["id"], "|", image["title"], "|", image["source_url"])
    print("Dane galerii Elementor wystawione przez REST:", result["elementor_meta_visible"])\n    print("Wykryte elementy Elementor:", len(elementor_widgets))\n    for widget in elementor_widgets:\n        if widget["widgetType"]:\n            print("Elementor widget:", widget["id"], widget["widgetType"], "pola:", widget["setting_keys"])
    print("Raport JSON zapisany jako artefakt wordpress-audit.")

if __name__ == "__main__":
    try:
        main()
    except urllib.error.HTTPError as e:
        print("Blad WordPress HTTP:", e.code, "(bez ujawniania danych uwierzytelnienia)", file=sys.stderr)
        sys.exit(1)
    except Exception as e:
        print("Blad audytu:", type(e).__name__, str(e).replace(PASSWORD, "[UKRYTE]"), file=sys.stderr)
        sys.exit(1)
