<?php
// Blog Category Page
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../../config/config.php';
}
if (!function_exists('getAppConfig')) {
    require_once __DIR__ . '/../../helpers/functions.php';
}
$pageTitle = ($category['name'] ?? 'Kategori') . ' - Qordy Blog';
$page = 'blog_category';
$seoParams = [];
?>

<?php include __DIR__ . '/../layouts/header.php'; ?>

<div class="container mx-auto px-4 py-8">
    <div class="mb-8">
        <nav class="mb-4 text-sm text-gray-600">
            <a href="<?php echo BASE_URL; ?>" class="hover:text-blue-600">Ana Sayfa</a>
            <span class="mx-2">/</span>
            <a href="<?php echo BASE_URL; ?>/blog" class="hover:text-blue-600">Blog</a>
            <span class="mx-2">/</span>
            <span class="text-gray-800"><?php echo htmlspecialchars($category['name']); ?></span>
        </nav>
        
        <h1 class="text-4xl font-bold text-gray-800 mb-4"><?php echo htmlspecialchars($category['name']); ?></h1>
        <?php if (!empty($category['description'])): ?>
            <p class="text-gray-600 text-lg"><?php echo htmlspecialchars($category['description']); ?></p>
        <?php endif; ?>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        <!-- Main Content -->
        <div class="lg:col-span-3">
            <?php if (empty($posts)): ?>
                <div class="bg-white rounded-lg shadow p-8 text-center">
                    <p class="text-gray-600">Bu kategoride henüz yazı bulunmamaktadır.</p>
                    <a href="<?php echo BASE_URL; ?>/blog" class="inline-block mt-4 text-blue-600 hover:text-blue-800">
                        ← Tüm yazılara dön
                    </a>
                </div>
            <?php else: ?>
                <div class="space-y-6">
                    <?php foreach ($posts as $post): ?>
                        <article class="bg-white rounded-lg shadow hover:shadow-lg transition-shadow overflow-hidden">
                            <?php if (!empty($post['featured_image'])): ?>
                                <img src="<?php echo htmlspecialchars($post['featured_image']); ?>" 
                                     alt="<?php echo htmlspecialchars($post['title']); ?>" 
                                     class="w-full h-64 object-cover">
                            <?php endif; ?>
                            
                            <div class="p-6">
                                <h2 class="text-2xl font-bold text-gray-800 mb-3">
                                    <a href="<?php echo BASE_URL; ?>/blog/<?php echo htmlspecialchars($post['slug']); ?>" 
                                       class="hover:text-blue-600 transition-colors">
                                        <?php echo htmlspecialchars($post['title']); ?>
                                    </a>
                                </h2>
                                
                                <?php if (!empty($post['excerpt'])): ?>
                                    <p class="text-gray-600 mb-4"><?php echo htmlspecialchars($post['excerpt']); ?></p>
                                <?php endif; ?>
                                
                                <div class="flex items-center justify-between text-sm text-gray-500">
                                    <div class="flex items-center space-x-4">
                                        <?php if (!empty($post['published_at'])): ?>
                                            <span><?php echo date('d.m.Y', strtotime($post['published_at'])); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($post['view_count'])): ?>
                                            <span><?php echo $post['view_count']; ?> görüntüleme</span>
                                        <?php endif; ?>
                                    </div>
                                    <a href="<?php echo BASE_URL; ?>/blog/<?php echo htmlspecialchars($post['slug']); ?>" 
                                       class="text-blue-600 hover:text-blue-800 font-semibold">
                                        Devamını Oku →
                                    </a>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if (isset($currentPage) && $currentPage > 1): ?>
                    <div class="mt-8 flex justify-center">
                        <a href="<?php echo BASE_URL; ?>/blog/category/<?php echo htmlspecialchars($category['slug']); ?>?page=<?php echo $currentPage - 1; ?>" 
                           class="px-4 py-2 bg-white border rounded hover:bg-gray-50">
                            Önceki Sayfa
                        </a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Sidebar -->
        <div class="lg:col-span-1">
            <!-- Categories -->
            <?php if (!empty($categories)): ?>
                <div class="bg-white rounded-lg shadow p-6 mb-6">
                    <h3 class="text-xl font-bold text-gray-800 mb-4">Kategoriler</h3>
                    <ul class="space-y-2">
                        <?php foreach ($categories as $cat): ?>
                            <li>
                                <a href="<?php echo BASE_URL; ?>/blog/category/<?php echo htmlspecialchars($cat['slug']); ?>" 
                                   class="text-gray-600 hover:text-blue-600 transition-colors <?php echo ($cat['category_id'] === $category['category_id']) ? 'font-semibold text-blue-600' : ''; ?>">
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Back to Blog -->
            <div class="bg-white rounded-lg shadow p-6">
                <a href="<?php echo BASE_URL; ?>/blog" 
                   class="block text-center bg-blue-600 text-white px-6 py-2 rounded-lg font-semibold hover:bg-blue-700 transition-colors">
                    ← Tüm Yazılar
                </a>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
