import re

path = "/var/www/vhosts/qordy.com/httpdocs/public/assets/css/business-theme.css"
with open(path, "r", encoding="utf-8") as f:
 content = f.read()

# 3) Make grid columns wider — kill the minmax(0, 1fr) squeeze
pattern = re.compile(
 r'#kitchen-dashboard \.kds-orders-grid \{[\s\S]*?grid-template-columns: repeat\(5, minmax\(0, 1fr\)\);[\s\S]*?\}',
 re.DOTALL
)
m = pattern.search(content)
if not m:
 print("Grid block not found")
 raise SystemExit(1)

indent = " "
replacement = (
 "#kitchen-dashboard .kds-orders-grid {\n"
 + indent + "display: grid;\n"
 + indent + "grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));\n"
 + indent + "gap: 1.25rem;\n"
 + indent + "align-items: stretch;\n"
 + "}\n\n"
 + "@media (max-width: 639px) {\n"
 + indent + "#kitchen-dashboard .kds-orders-grid {\n"
 + indent + indent + "grid-template-columns: 1fr;\n"
 + indent + "}\n"
 + "}\n\n"
 + "@media (min-width: 1536px) {\n"
 + indent + "#kitchen-dashboard .kds-orders-grid {\n"
 + indent + indent + "gap: 1.5rem;\n"
 + indent + "}\n"
 + "}"
)
content = content.replace(m.group(0), replacement, 1)
print("Grid fixed")

with open(path, "w", encoding="utf-8") as f:
 f.write(content)
