#!/usr/bin/env python3
"""Pre-merge checks for the Al-Falah static site.

Fails (exit 1) when:
  1. index.html is missing from the repo root
  2. archives, databases, dumps or key files are tracked in Git
  3. a hardcoded secret pattern is found in tracked text files
  4. a local page, image, script or style reference points to a file that does not exist
     (known gaps can be listed in scripts/known-missing.txt)

Usage:  python3 scripts/check_site.py [repo_root]
"""
import os
import re
import subprocess
import sys

ROOT = os.path.abspath(sys.argv[1] if len(sys.argv) > 1 else os.path.join(os.path.dirname(__file__), ".."))

FORBIDDEN_FILES = re.compile(
    r"(\.(zip|tar|gz|tgz|7z|rar|sql|db|sqlite3?|pem|key)$|(^|/)\.env($|\.)|(^|/)config\.local\.php$)", re.I
)
SECRET_PATTERNS = [
    (re.compile(r"define\(\s*['\"]ADMIN_PASS['\"]\s*,\s*['\"][^'\"]+['\"]"), "hardcoded ADMIN_PASS"),
    (re.compile(r"\bsk-[A-Za-z0-9_-]{20,}"), "API key (sk-...)"),
    (re.compile(r"-----BEGIN [A-Z ]*PRIVATE KEY-----"), "private key"),
    (re.compile(r"\bAKIA[0-9A-Z]{16}\b"), "AWS access key"),
]
TEXT_EXT = (".html", ".css", ".js", ".php", ".json", ".xml", ".txt", ".md", ".yml", ".yaml", ".htaccess", ".py")
ATTR = re.compile(r"""(?:src|href|data-src|poster)\s*=\s*["']([^"'#?]+)""", re.I)
CSS_URL = re.compile(r"""url\(\s*['"]?([^'")?#]+)""", re.I)
FILE_LIKE = re.compile(
    r"\.(html|js|css|jpe?g|png|webp|svg|gif|ico|mp4|webm|woff2?|ttf|json|xml|txt|pdf|php)$", re.I
)
SKIP_URL = re.compile(r"(?i)^(https?:|//|mailto:|tel:|data:|javascript:|whatsapp:|sms:)")


def tracked_files():
    out = subprocess.run(["git", "-C", ROOT, "ls-files", "-z"], capture_output=True, check=True).stdout
    return [f for f in out.decode("utf-8", "replace").split("\0") if f]


def main():
    errors = []
    files = tracked_files()

    if "index.html" not in files:
        errors.append("index.html is missing from the repo root")

    for f in files:
        if FORBIDDEN_FILES.search(f):
            errors.append(f"forbidden file tracked in Git: {f}")

    for f in files:
        if not f.lower().endswith(TEXT_EXT) and os.path.basename(f) != ".htaccess":
            continue
        if f.startswith("scripts/"):
            continue  # this file contains the patterns themselves
        try:
            text = open(os.path.join(ROOT, f), encoding="utf-8", errors="ignore").read()
        except OSError:
            continue
        code = "\n".join(
            line for line in text.splitlines() if not line.lstrip().startswith(("//", "#", "*", "/*"))
        )  # commented-out examples are not secrets
        for pat, label in SECRET_PATTERNS:
            if pat.search(code):
                errors.append(f"possible secret ({label}) in {f}")

    known = set()
    km = os.path.join(ROOT, "scripts", "known-missing.txt")
    if os.path.exists(km):
        known = {l.strip() for l in open(km, encoding="utf-8") if l.strip() and not l.startswith("#")}

    missing = {}
    checked = 0
    for f in files:
        if not f.lower().endswith((".html", ".css", ".js")):
            continue
        base = os.path.dirname(os.path.join(ROOT, f))
        text = open(os.path.join(ROOT, f), encoding="utf-8", errors="ignore").read()
        for u in ATTR.findall(text) + CSS_URL.findall(text):
            u = u.strip()
            if not u or SKIP_URL.match(u) or u[0] in "{$<" or not (FILE_LIKE.search(u) or u.endswith("/")):
                continue
            checked += 1
            target = os.path.normpath(os.path.join(ROOT, u.lstrip("/")) if u.startswith("/") else os.path.join(base, u))
            if u.endswith("/"):
                target = os.path.join(target, "index.html")
            if not os.path.exists(target):
                rel = os.path.relpath(target, ROOT).replace(os.sep, "/")
                if rel not in known:
                    missing.setdefault(rel, set()).add(f)

    for rel, users in sorted(missing.items()):
        errors.append(f"broken reference: {rel}  (used in {', '.join(sorted(users)[:3])})")

    print(f"Checked {len(files)} tracked files and {checked} local references.")
    if known:
        print(f"Known gaps allowed: {len(known)} (see scripts/known-missing.txt)")
    if errors:
        print(f"\n{len(errors)} problem(s):")
        for e in errors:
            print(f"  - {e}")
        return 1
    print("All checks passed.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
