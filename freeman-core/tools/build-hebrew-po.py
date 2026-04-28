#!/usr/bin/env python3
"""
Generate freeman-core-he_IL.po from freeman-core.pot using a curated
translation map. Unknown strings are left with an empty msgstr so they
fall back to English — that's intentional; the map can grow over time.

Usage:
    python3 tools/build-hebrew-po.py
"""
from __future__ import annotations
import pathlib
import re
import sys

HE = {
    "Freeman": "פרימן",
    "Dashboard": "לוח בקרה",
    "Tools": "כלים",
    "Settings": "הגדרות",
    "General": "כללי",
    "Status": "סטטוס",
    "Enable": "הפעל",
    "Disable": "השבת",
    "Active": "פעיל",
    "Disabled": "מושבת",
    "Module": "מודול",
    "Imported": "יובא",
    "Missing dependency": "תלות חסרה",
    "Insufficient permissions.": "אין הרשאה.",
    "Security check failed.": "אימות האבטחה נכשל.",
    "Security token missing.": "חסר אסימון אבטחה.",
    "Save & continue": "שמור והמשך",
    "Skip onboarding": "דלג על ההדרכה",
    "Welcome to Freeman": "ברוכים הבאים לפרימן",
    "Open the dashboard": "פתח את לוח הבקרה",
    "Freeman Core is installed — pick which modules to activate.": "פרימן קור הותקן — בחר אילו מודולים להפעיל.",
    "Freeman gives you seven WooCommerce super-powers in one plugin. Pick what you want now — you can change this any time from the Dashboard.": "פרימן נותן לך שבעה סופר-כוחות של ווקומרס בתוסף אחד. בחר עכשיו מה להפעיל — תוכל לשנות בכל עת בלוח הבקרה.",
    "This module is disabled. Enable it from the Dashboard.": "המודול הזה מושבת. אפשר להפעיל אותו דרך לוח הבקרה.",

    # Module names
    "Cheapest Default Variation": "וריאציה זולה כברירת מחדל",
    "Infinite Scroll": "גלילה אינסופית",
    "Variable Stock Fix": "תיקון מלאי למוצרים עם וריאציות",
    "Product Feed": "פיד מוצרים",
    "Variation Swatches": "דוגמאות וריאציה",
    "Restock Notify": "התראת חידוש מלאי",

    # Module descriptions
    "Auto-selects the cheapest in-stock variation as the default so customers can add to cart without picking options.": "בוחר אוטומטית את הוריאציה הזולה ביותר שבמלאי כברירת מחדל כדי שלקוחות יוכלו להוסיף לסל בלי לבחור אופציות.",
    "Color swatches, size pills, quick-add buy box and shop-grid variation picker for variable products.": "דוגמאות צבע, כפתורי מידה, קופסת קנייה מהירה ובוחר וריאציות בעמוד הקטגוריה — למוצרים עם וריאציות.",
    "Customers subscribe to out-of-stock products and are emailed the moment stock returns. Hebrew-first UI, exportable subscriber list.": "לקוחות נרשמים למוצרים שאזלו מהמלאי ומקבלים התראה במייל ברגע שהמלאי חוזר. ממשק בעברית וייצוא רשימת נרשמים.",
    "When all visible variations of a variable product are out of stock, this module unchecks the parent\u2019s \"Manage stock\" box so Woo\u2019s native \"Hide out of stock items\" hides the product from the shop.": "כאשר כל הוריאציות הגלויות של מוצר אזלו מהמלאי, המודול מסיר את סימון \u201cניהול מלאי\u201d כך שווקומרס מסתירה את המוצר מהחנות.",

    # Infinite scroll settings
    "Skeleton cards": "כרטיסי שלד",
    "How many placeholder cards to show while loading.": "כמה כרטיסי מלא-מקום להציג בזמן הטעינה.",
    "Max pages": "מספר דפים מקסימלי",
    "Absolute safety limit \u2014 no more than this many pages will ever auto-load.": "גבול בטיחות מוחלט — אף פעם לא ייטענו אוטומטית יותר דפים מזה.",
    "End-of-list message": "הודעת סוף רשימה",
    "Shown once there are no more products.": "מוצגת כשנגמרו המוצרים.",
    "You have reached the end.": "הגעת לסוף.",
    "Load more": "טען עוד",
    "Could not load more.": "לא ניתן לטעון עוד.",

    # Variable Stock Fix
    "Respect manual defaults": "כבד ברירות מחדל ידניות",
    "Leave defaults set in the product editor alone": "השאר ברירות מחדל שהוגדרו בעורך המוצר",
    "When on, manually chosen defaults take precedence over this automatic selection.": "כשהאפשרות פעילה, ברירות מחדל שנבחרו ידנית גוברות על הבחירה האוטומטית.",
    "Daily audit": "ביקורת יומית",
    "Run a daily audit of recently modified variable products": "הרץ ביקורת יומית על מוצרי וריאציה שעודכנו לאחרונה",
    "Safety-net: once per day the module scans products modified in the last 48h and fixes any that match.": "רשת בטיחות: פעם ביום המודול סורק מוצרים שעודכנו ב-48 השעות האחרונות ומתקן את אלה שמתאימים.",
    "Bulk audit": "ביקורת בתפזורת",
    "Start scan": "התחל סריקה",
    "Stop": "עצור",
    "Stopped.": "הופסק.",
    "Done.": "הסתיים.",
    "Counting variable products\u2026": "סופר מוצרים עם וריאציות\u2026",
    "Scanning batch starting at": "סורק אצווה החל מ",
    "Fixing batch starting at": "מתקן אצווה החל מ",
    "Fixed %s": "תוקנו %s",
    "Would fix %s": "היה מתקן %s",
    "scanned": "נסרק",
    "match": "תואם",
    "fixed": "תוקן",
    "missing": "חסר",
    "not installed": "לא מותקן",
    "Dry-run is OFF. This will actually modify products. Continue?": "מצב הרצה-יבשה כבוי. השינויים ייכנסו לתוקף על המוצרים. להמשיך?",
    "Scans every variable product. For each one where every visible variation is out of stock AND the parent still has \"Manage stock\" checked, the module unchecks that box and clears the parent stock quantity.": "סורק את כל המוצרים עם וריאציות. לכל מוצר שבו כל הוריאציות הגלויות אזלו ועדיין מסומן \u201cניהול מלאי\u201d באב — המודול מסיר את הסימון ומנקה את כמות המלאי באב.",

    # Product Feed
    "Instant updates": "עדכונים מיידיים",
    "Rebuild within ~30 seconds of any stock or price change": "בונה מחדש תוך כ-30 שניות מכל שינוי מלאי או מחיר",
    "Hourly fallback": "גיבוי שעתי",
    "Feed URL": "כתובת הפיד",
    "Feed status": "סטטוס הפיד",
    "Feed generated successfully.": "הפיד נוצר בהצלחה.",
    "Generate now": "צור עכשיו",
    "Last generated": "נוצר לאחרונה",
    "Next hourly run": "ריצה שעתית הבאה",
    "Instant rebuild queued": "בנייה מחדש מיידית נכנסה לתור",
    "Not generated yet": "עדיין לא נוצר",
    "Not scheduled": "לא מתוזמן",
    "Never": "אף פעם",

    # Legacy importer
    "Legacy plugin import": "ייבוא תוספים ישנים",
    "Freeman Core can import data from the legacy plugins it replaces. Importing is non-destructive \u2014 legacy plugin files remain installed until you run the delete step below.": "פרימן קור יכול לייבא נתונים מהתוספים הישנים שהוא מחליף. הייבוא אינו הרסני — קבצי התוספים הישנים נשארים מותקנים עד שתריץ את שלב המחיקה למטה.",
    "Legacy plugin": "תוסף ישן",
    "Legacy plugin active": "התוסף הישן פעיל",
    "Installed (inactive)": "מותקן (לא פעיל)",
    "Not installed": "לא מותקן",
    "No importer": "אין מייבא",
    "Run legacy import": "הרץ ייבוא ישן",
    "Deactivate & delete legacy plugins": "בטל ומחק תוספים ישנים",
    "No legacy plugins detected. Nothing to delete.": "לא זוהו תוספים ישנים. אין מה למחוק.",
    "This will deactivate and permanently delete every detected legacy plugin. Continue?": "הפעולה תבטל ותמחק לצמיתות את כל התוספים הישנים שזוהו. להמשיך?",
    "Import complete.": "הייבוא הסתיים.",
    "Legacy plugins deactivated and deleted.": "התוספים הישנים בוטלו ונמחקו.",
    "Freeman Tools": "כלים של פרימן",
    "Recent log": "לוג אחרון",
    "No log entries yet.": "עדיין אין רשומות בלוג.",
    "Cheapest-variation snippet adopted; nothing to migrate.": "סקריפט הוריאציה הזולה אומץ במקום; אין מה להעביר.",
    "Variable Stock Fix migrated \u2014 legacy daily audit cron cleared.": "מודול תיקון המלאי הועבר — משימת ה-cron היומית הישנה נוקתה.",
    "Product Feed settings migrated \u2014 legacy cron cleared.": "הגדרות פיד המוצרים הועברו — משימות ה-cron הישנות נוקו.",
    "Restock Notify subscribers and settings adopted in place.": "הנרשמים וההגדרות של Restock Notify אומצו במקום.",
    "No": "לא",
}

def unescape(s: str) -> str:
    # Undo PO-style backslash escaping so we can key the Python dict by the
    # human-readable string.
    return s.replace('\\n', '\n').replace('\\"', '"').replace('\\\\', '\\')

def escape(s: str) -> str:
    return s.replace('\\', '\\\\').replace('"', '\\"').replace('\n', '\\n')

def main():
    pot = pathlib.Path('languages/freeman-core.pot').read_text(encoding='utf-8')
    out_path = pathlib.Path('languages/freeman-core-he_IL.po')

    # Flush through blocks.
    blocks = re.split(r'\n(?=#[:\.,]|msgid )', pot)
    out_lines = []
    header_done = False
    for block in blocks:
        if 'msgid ""' in block and not header_done:
            header = re.sub(r'"Language: \\n"', r'"Language: he_IL\\n"', block)
            header = re.sub(r'"Plural-Forms: [^"]*"', r'"Plural-Forms: nplurals=2; plural=(n != 1);"', header)
            header = re.sub(r'"PO-Revision-Date: [^"]*"', r'"PO-Revision-Date: 2026-04-22 00:00+0000"', header)
            out_lines.append(header)
            header_done = True
            continue
        m = re.search(r'msgid "(.*?)"\s*\nmsgstr ""', block, flags=re.DOTALL)
        if m:
            raw = m.group(1)
            key = unescape(raw)
            if key in HE:
                translated = escape(HE[key])
                block = block.replace('msgstr ""', f'msgstr "{translated}"', 1)
        out_lines.append(block)

    out_path.write_text('\n'.join(out_lines), encoding='utf-8')
    print(f'wrote {out_path}')

if __name__ == '__main__':
    main()
