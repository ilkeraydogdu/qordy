<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($siteName ?? getAppConfig()->getAppName()); ?> - <?php echo htmlspecialchars($emailTitle ?? 'Email'); ?></title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div class="q-gradient-brand" style="padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
        <h1 style="color: white; margin: 0; font-size: 28px;"><?php echo htmlspecialchars($siteName ?? getAppConfig()->getAppName()); ?></h1>
    </div>
    <div style="background: #ffffff; padding: 30px; border: 1px solid #e5e7eb; border-top: none; border-radius: 0 0 10px 10px;">
        <?php echo $content; ?>
        
        <?php if (!empty($restaurantPhone) || !empty($restaurantAddress)): ?>
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
            <?php if (!empty($restaurantPhone)): ?>
            <p style="margin: 5px 0;"><strong>Telefon:</strong> <?php echo htmlspecialchars($restaurantPhone); ?></p>
            <?php endif; ?>
            <?php if (!empty($restaurantAddress)): ?>
            <p style="margin: 5px 0;"><strong>Adres:</strong> <?php echo htmlspecialchars($restaurantAddress); ?></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <div style="text-align: center; margin-top: 20px; color: #9ca3af; font-size: 12px;">
        <p>Bu otomatik bir e-postadır. Lütfen yanıtlamayın.</p>
    </div>
</body>
</html>

