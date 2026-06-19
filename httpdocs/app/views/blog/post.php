<?php
// Blog Post Detail Page
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../../config/config.php';
}
if (!function_exists('getAppConfig')) {
    require_once __DIR__ . '/../../helpers/functions.php';
}
$pageTitle = ($post['meta_title'] ?? $post['title']) . ' - Qordy Blog';
$page = 'blog_post';
$seoParams = [];
$customSEOTags = '';
if (!empty($post['meta_description'])) {
    $customSEOTags .= '<meta name="description" content="' . htmlspecialchars($post['meta_description']) . '">';
}
if (!empty($post['meta_keywords'])) {
    $customSEOTags .= '<meta name="keywords" content="' . htmlspecialchars($post['meta_keywords']) . '">';
}
$customSEOTags .= '<link rel="canonical" href="' . BASE_URL . '/blog/' . htmlspecialchars($post['slug']) . '">';
?>

<?php include __DIR__ . '/../layouts/header.php'; ?>

<div class="container mx-auto px-4 py-8">
    <article class="max-w-4xl mx-auto">
        <!-- Breadcrumb -->
        <nav class="mb-6 text-sm text-gray-600">
            <a href="<?php echo BASE_URL; ?>" class="hover:text-blue-600">Ana Sayfa</a>
            <span class="mx-2">/</span>
            <a href="<?php echo BASE_URL; ?>/blog" class="hover:text-blue-600">Blog</a>
            <?php if (!empty($post['category_name'])): ?>
                <span class="mx-2">/</span>
                <a href="<?php echo BASE_URL; ?>/blog/category/<?php echo htmlspecialchars($post['category_slug']); ?>" 
                   class="hover:text-blue-600">
                    <?php echo htmlspecialchars($post['category_name']); ?>
                </a>
            <?php endif; ?>
            <span class="mx-2">/</span>
            <span class="text-gray-800"><?php echo htmlspecialchars($post['title']); ?></span>
        </nav>

        <!-- Header -->
        <header class="mb-8">
            <?php if (!empty($post['category_name'])): ?>
                <a href="<?php echo BASE_URL; ?>/blog/category/<?php echo htmlspecialchars($post['category_slug']); ?>" 
                   class="inline-block bg-blue-100 text-blue-800 text-sm px-3 py-1 rounded-full mb-4 hover:bg-blue-200 transition-colors">
                    <?php echo htmlspecialchars($post['category_name']); ?>
                </a>
            <?php endif; ?>
            
            <h1 class="text-4xl font-bold text-gray-800 mb-4"><?php echo htmlspecialchars($post['title']); ?></h1>
            
            <div class="flex items-center text-sm text-gray-600 mb-6">
                <?php if (!empty($post['published_at'])): ?>
                    <time datetime="<?php echo date('Y-m-d', strtotime($post['published_at'])); ?>">
                        <?php echo date('d F Y', strtotime($post['published_at'])); ?>
                    </time>
                <?php endif; ?>
                <?php if (!empty($post['view_count'])): ?>
                    <span class="ml-4"><?php echo $post['view_count']; ?> görüntüleme</span>
                <?php endif; ?>
            </div>

            <?php if (!empty($post['featured_image'])): ?>
                <img src="<?php echo htmlspecialchars($post['featured_image']); ?>" 
                     alt="<?php echo htmlspecialchars($post['title']); ?>" 
                     class="w-full h-96 object-cover rounded-lg mb-6">
            <?php endif; ?>

            <?php if (!empty($post['excerpt'])): ?>
                <p class="text-xl text-gray-600 leading-relaxed"><?php echo htmlspecialchars($post['excerpt']); ?></p>
            <?php endif; ?>
        </header>

        <!-- Content -->
        <div class="prose prose-lg max-w-none mb-8">
            <?php echo $post['content']; ?>
        </div>

        <!-- Tags -->
        <?php if (!empty($post['tags']) && is_array($post['tags'])): ?>
            <div class="mb-8">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Etiketler:</h3>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($post['tags'] as $tag): ?>
                        <span class="bg-gray-100 text-gray-700 text-sm px-3 py-1 rounded-full">
                            <?php echo htmlspecialchars($tag); ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Share Buttons -->
        <div class="border-t border-b py-6 mb-8">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Paylaş:</h3>
            <div class="flex space-x-4">
                <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode(BASE_URL . '/blog/' . $post['slug']); ?>" 
                   target="_blank"
                   class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition-colors">
                    Facebook
                </a>
                <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode(BASE_URL . '/blog/' . $post['slug']); ?>&text=<?php echo urlencode($post['title']); ?>" 
                   target="_blank"
                   class="bg-blue-400 text-white px-4 py-2 rounded hover:bg-blue-500 transition-colors">
                    Twitter
                </a>
                <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?php echo urlencode(BASE_URL . '/blog/' . $post['slug']); ?>" 
                   target="_blank"
                   class="bg-blue-800 text-white px-4 py-2 rounded hover:bg-blue-900 transition-colors">
                    LinkedIn
                </a>
            </div>
        </div>

        <!-- Related Posts -->
        <?php if (!empty($relatedPosts)): ?>
            <div class="mt-12">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">İlgili Yazılar</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php foreach ($relatedPosts as $related): ?>
                        <article class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition-shadow">
                            <?php if (!empty($related['featured_image'])): ?>
                                <img src="<?php echo htmlspecialchars($related['featured_image']); ?>" 
                                     alt="<?php echo htmlspecialchars($related['title']); ?>" 
                                     class="w-full h-48 object-cover rounded mb-4">
                            <?php endif; ?>
                            
                            <h3 class="text-xl font-bold text-gray-800 mb-2">
                                <a href="<?php echo BASE_URL; ?>/blog/<?php echo htmlspecialchars($related['slug']); ?>" 
                                   class="hover:text-blue-600 transition-colors">
                                    <?php echo htmlspecialchars($related['title']); ?>
                                </a>
                            </h3>
                            
                            <?php if (!empty($related['excerpt'])): ?>
                                <p class="text-gray-600 text-sm mb-4"><?php echo htmlspecialchars(substr($related['excerpt'], 0, 150)); ?>...</p>
                            <?php endif; ?>
                            
                            <a href="<?php echo BASE_URL; ?>/blog/<?php echo htmlspecialchars($related['slug']); ?>" 
                               class="text-blue-600 hover:text-blue-800 text-sm font-semibold">
                                Devamını Oku →
                            </a>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </article>
</div>

<style>
.prose {
    color: #374151;
    line-height: 1.75;
}

.prose h2 {
    font-size: 1.875rem;
    font-weight: 700;
    margin-top: 2rem;
    margin-bottom: 1rem;
    color: #1f2937;
}

.prose h3 {
    font-size: 1.5rem;
    font-weight: 600;
    margin-top: 1.5rem;
    margin-bottom: 0.75rem;
    color: #374151;
}

.prose p {
    margin-bottom: 1.25rem;
}

.prose ul, .prose ol {
    margin-bottom: 1.25rem;
    padding-left: 1.625rem;
}

.prose li {
    margin-bottom: 0.5rem;
}

.prose a {
    color: #2563eb;
    text-decoration: underline;
}

.prose a:hover {
    color: #1d4ed8;
}

.prose img {
    border-radius: 0.5rem;
    margin: 1.5rem 0;
}
</style>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
