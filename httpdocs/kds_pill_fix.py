import re

css_path = "/var/www/vhosts/qordy.com/httpdocs/public/assets/css/business-theme.css"
fh = open(css_path, "r", encoding="utf-8")
css = fh.read()
fh.close()

# Find the kds-stat-pill block
pattern = re.compile(
 r'#kitchen-dashboard \.kds-stat-pill \{[^}]*?\}',
 re.DOTALL
)
m = pattern.search(css)
if m:
 indent = " "
 replacement = (
 "#kitchen-dashboard .kds-stat-pill {\n"
 + indent + "display: inline-flex;\n"
 + indent + "align-items: center;\n"
 + indent + "gap: 0.5rem;\n"
 + indent + "padding: 0.625rem 1.125rem;\n"
 + indent + "background: #ffffff;\n"
 + indent + "border: 1.5px solid var(--biz-ink-200, #e2e8f0);\n"
 + indent + "border-radius: 0.875rem;\n"
 + indent + "font-size: 0.8125rem;\n"
 + indent + "font-weight: 800;\n"
 + indent + "letter-spacing: 0.04em;\n"
 + indent + "text-transform: uppercase;\n"
 + indent + "color: #475569;\n"
 + indent + "box-shadow: 0 4px 12px -4px rgba(15, 18, 40, 0.08);\n"
 + indent + "white-space: nowrap;\n"
 + "}"
 )
 css = css.replace(m.group(0), replacement, 1)
 print("Enhanced kds-stat-pill")
 fh = open(css_path, "w", encoding="utf-8")
 fh.write(css)
 fh.close()
else:
 print("Pattern not found")
