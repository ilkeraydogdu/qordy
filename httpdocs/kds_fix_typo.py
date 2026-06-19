path = "/var/www/vhosts/qordy.com/httpdocs/app/views/kitchen/dashboard.php"
with open(path, "r", encoding="utf-8") as f:
 content = f.read()

bad = '<?php echo $isSuperAdmin ? \'display: none;\' : \'\'; ?>">">'
good = '<?php echo $isSuperAdmin ? \'display: none;\' : \'\'; ?>">'
if bad in content:
 content = content.replace(bad, good, 1)
 print("Fixed extra quote")
else:
 print("Pattern not found")

with open(path, "w", encoding="utf-8") as f:
 f.write(content)
