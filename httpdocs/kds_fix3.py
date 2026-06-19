import re

path = "/var/www/vhosts/qordy.com/httpdocs/public/assets/css/business-theme.css"
with open(path, "r", encoding="utf-8") as f:
 content = f.read()

# 4) Order card: improve padding and inner spacing
pattern = re.compile(
 r'#kitchen-dashboard \.kds-order-card \{[^}]*?max-height: 85vh;[^}]*?\}',
 re.DOTALL
)
m = pattern.search(content)
if m:
 indent = " "
 replacement = (
 "#kitchen-dashboard .kds-order-card {\n"
 + indent + "display: flex;\n"
 + indent + "flex-direction: column;\n"
 + indent + "min-width: 0;\n"
 + indent + "background: #ffffff;\n"
 + indent + "border: 1.5px solid var(--biz-ink-200, #e2e8f0);\n"
 + indent + "border-radius: 1rem;\n"
 + indent + "box-shadow: 0 8px 24px -12px rgba(15, 18, 40, 0.12);\n"
 + indent + "transition: border-color 0.25s ease, box-shadow 0.25s ease, transform 0.2s ease;\n"
 + indent + "overflow: hidden;\n"
 + "}"
 )
 content = content.replace(m.group(0), replacement, 1)
 print("Fixed order card base")

# 5) Empty state — better proportion
pattern = re.compile(
 r'#kitchen-dashboard \.kds-empty \{[^}]*?\}',
 re.DOTALL
)
m = pattern.search(content)
if m:
 indent = " "
 replacement = (
 "#kitchen-dashboard .kds-empty {\n"
 + indent + "display: flex;\n"
 + indent + "flex-direction: column;\n"
 + indent + "align-items: center;\n"
 + indent + "justify-content: center;\n"
 + indent + "min-height: 60vh;\n"
 + indent + "padding: 2rem 0;\n"
 + "}"
 )
 content = content.replace(m.group(0), replacement, 1)
 print("Fixed empty state container")

# 6) Empty state card — make it roomy
pattern = re.compile(
 r'#kitchen-dashboard \.kds-empty__card \{[^}]*?\}',
 re.DOTALL
)
m = pattern.search(content)
if m:
 indent = " "
 replacement = (
 "#kitchen-dashboard .kds-empty__card {\n"
 + indent + "padding: 3rem 4rem;\n"
 + indent + "background: #ffffff;\n"
 + indent + "border: 1.5px solid var(--biz-ink-200, #e2e8f0);\n"
 + indent + "border-radius: 1.25rem;\n"
 + indent + "box-shadow: 0 14px 44px -28px rgba(15, 18, 40, 0.14);\n"
 + indent + "text-align: center;\n"
 + indent + "max-width: 32rem;\n"
 + "}"
 )
 content = content.replace(m.group(0), replacement, 1)
 print("Fixed empty state card")

# 7) Empty state icon — bigger
pattern = re.compile(
 r'#kitchen-dashboard \.kds-empty__icon \{[^}]*?\}',
 re.DOTALL
)
m = pattern.search(content)
if m:
 indent = " "
 replacement = (
 "#kitchen-dashboard .kds-empty__icon {\n"
 + indent + "width: 5rem;\n"
 + indent + "height: 5rem;\n"
 + indent + "margin: 0 auto 1.25rem;\n"
 + indent + "color: #cbd5e1;\n"
 + "}"
 )
 content = content.replace(m.group(0), replacement, 1)
 print("Fixed empty state icon")

# 8) Title and hint
pattern = re.compile(
 r'#kitchen-dashboard \.kds-empty__title \{[^}]*?\}',
 re.DOTALL
)
m = pattern.search(content)
if m:
 indent = " "
 replacement = (
 "#kitchen-dashboard .kds-empty__title {\n"
 + indent + "font-size: 1.375rem;\n"
 + indent + "font-weight: 800;\n"
 + indent + "color: #475569;\n"
 + indent + "letter-spacing: -0.02em;\n"
 + "}"
 )
 content = content.replace(m.group(0), replacement, 1)
 print("Fixed empty title")

pattern = re.compile(
 r'#kitchen-dashboard \.kds-empty__hint \{[^}]*?\}',
 re.DOTALL
)
m = pattern.search(content)
if m:
 indent = " "
 replacement = (
 "#kitchen-dashboard .kds-empty__hint {\n"
 + indent + "margin-top: 0.625rem;\n"
 + indent + "font-size: 0.9375rem;\n"
 + indent + "color: #94a3b8;\n"
 + indent + "font-weight: 500;\n"
 + "}"
 )
 content = content.replace(m.group(0), replacement, 1)
 print("Fixed empty hint")

# 9) Order card head — give it better padding
pattern = re.compile(
 r'#kitchen-dashboard \.kds-order-card__head \{[^}]*?\}',
 re.DOTALL
)
m = pattern.search(content)
if m:
 indent = " "
 replacement = (
 "#kitchen-dashboard .kds-order-card__head {\n"
 + indent + "padding: 1.125rem 1.375rem;\n"
 + indent + "border-bottom: 1px solid #f1f5f9;\n"
 + indent + "flex-shrink: 0;\n"
 + "}"
 )
 content = content.replace(m.group(0), replacement, 1)
 print("Fixed card head")

# 10) Order item — breathing room
pattern = re.compile(
 r'#kitchen-dashboard \.kds-order-item \{[^}]*?\}',
 re.DOTALL
)
m = pattern.search(content)
if m:
 indent = " "
 replacement = (
 "#kitchen-dashboard .kds-order-item {\n"
 + indent + "padding: 1rem 1.375rem;\n"
 + indent + "border-bottom: 1px solid #f1f5f9;\n"
 + indent + "transition: background 0.15s ease;\n"
 + "}"
 )
 content = content.replace(m.group(0), replacement, 1)
 print("Fixed order item padding")

with open(path, "w", encoding="utf-8") as f:
 f.write(content)
print("All done")
