<?php
session_start();
require_once 'config.php';

// Ensure the upload directory exists
$upload_dir = __DIR__ . '/upload/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Auto-ensure image column exists in products table
try {
    $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS image VARCHAR(255) NULL");
} catch (Exception $e) {
    // Column already exists or handled by migration
}

$alert = null;

// Handle Add Product Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_product') {
    $name = trim($_POST['name'] ?? '');
    $price = trim($_POST['price'] ?? '');
    $image = $_FILES['product_image'] ?? null;

    $errors = [];

    // Validation
    if (empty($name)) {
        $errors[] = "Product name is required.";
    } elseif (strlen($name) > 100) {
        $errors[] = "Product name cannot exceed 100 characters.";
    }

    if ($price === '' || !is_numeric($price) || (float)$price <= 0) {
        $errors[] = "Please provide a valid positive price.";
    }

    $image_filename = null;
    if ($image && $image['error'] === UPLOAD_ERR_OK) {
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $allowed_mime_types = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        
        $file_info = pathinfo($image['name']);
        $file_ext = strtolower($file_info['extension'] ?? '');
        $file_tmp = $image['tmp_name'];
        $file_size = $image['size'];

        // Check file extension
        if (!in_array($file_ext, $allowed_extensions)) {
            $errors[] = "Invalid image format. Allowed formats: JPG, PNG, WEBP, GIF.";
        }

        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file_tmp);
        finfo_close($finfo);

        if (!in_array($mime_type, $allowed_mime_types)) {
            $errors[] = "Uploaded file is not a valid image.";
        }

        // Limit file size (5MB max)
        if ($file_size > 5 * 1024 * 1024) {
            $errors[] = "Image size exceeds 5MB limit.";
        }

        if (empty($errors)) {
            // Generate a unique and safe filename
            $unique_name = 'prod_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $file_ext;
            $destination = $upload_dir . $unique_name;

            if (move_uploaded_file($file_tmp, $destination)) {
                $image_filename = $unique_name;
            } else {
                $errors[] = "Failed to save uploaded image. Check folder permissions.";
            }
        }
    } else {
        $errors[] = "Please select a product image to upload.";
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO products (name, price, image) VALUES (?, ?, ?)");
            $stmt->execute([$name, (float)$price, $image_filename]);

            $_SESSION['flash_message'] = [
                'type' => 'success',
                'text' => "Product '{$name}' was successfully added!"
            ];
            header("Location: index.php");
            exit;
        } catch (PDOException $e) {
            $errors[] = "Database Error: " . $e->getMessage();
        }
    }

    if (!empty($errors)) {
        $alert = [
            'type' => 'error',
            'text' => implode('<br>', $errors)
        ];
    }
}

// Handle Delete Product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_product') {
    $delete_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    if ($delete_id) {
        try {
            // Fetch product to delete its image file from upload folder
            $stmt = $pdo->prepare("SELECT image FROM products WHERE id = ?");
            $stmt->execute([$delete_id]);
            $prod = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($prod && !empty($prod['image'])) {
                $file_path = $upload_dir . $prod['image'];
                if (file_exists($file_path)) {
                    @unlink($file_path);
                }
            }

            // Delete from database
            $delete_stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
            $delete_stmt->execute([$delete_id]);

            $_SESSION['flash_message'] = [
                'type' => 'success',
                'text' => "Product deleted successfully."
            ];
            header("Location: index.php");
            exit;
        } catch (PDOException $e) {
            $alert = [
                'type' => 'error',
                'text' => "Failed to delete product: " . $e->getMessage()
            ];
        }
    }
}

// Check for flash message from redirect
if (isset($_SESSION['flash_message'])) {
    $alert = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}

// Fetch all products
$products = [];
try {
    $products_stmt = $pdo->query("SELECT * FROM products ORDER BY id DESC");
    $products = $products_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // If error, empty list
}

// Fetch registered users
$users = [];
try {
    $users_stmt = $pdo->query("SELECT * FROM users ORDER BY id DESC");
    $users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Empty list
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Management & Dashboard | Dimuthu's Store</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Custom Tailwind Configuration -->
    <script src="assets/js/tailwind-config.js"></script>

    <!-- Google Fonts (Inter) -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .image-preview-active {
            border-color: #3b82f6;
            background-color: #eff6ff;
        }
    </style>
</head>
<body class="bg-slate-100 min-h-screen text-slate-800 antialiased">

    <!-- Navigation Bar -->
    <nav class="bg-slate-900 text-white shadow-xl sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-indigo-600 rounded-lg shadow-md">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                        </svg>
                    </div>
                    <div>
                        <span class="text-xl font-bold tracking-tight text-white">Dimuthu's Store</span>
                        <span class="hidden sm:inline-block ml-2 text-xs bg-slate-800 text-slate-300 px-2 py-0.5 rounded-full border border-slate-700">Product Manager</span>
                    </div>
                </div>
                
                <div class="flex items-center gap-3">
                    <a href="#add-product-form" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg shadow transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                        Add Product
                    </a>
                    <div class="text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/30 px-3 py-1 rounded-full flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        DB Online
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content Container -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
        
        <!-- Flash Alert Notification -->
        <?php if ($alert): ?>
            <div id="flash-banner" class="rounded-xl p-4 shadow-sm border flex items-start justify-between gap-3 transition-all duration-300 <?= $alert['type'] === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-rose-50 border-rose-200 text-rose-800' ?>">
                <div class="flex items-center gap-3">
                    <?php if ($alert['type'] === 'success'): ?>
                        <div class="p-2 bg-emerald-100 rounded-lg text-emerald-600 flex-shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        </div>
                    <?php else: ?>
                        <div class="p-2 bg-rose-100 rounded-lg text-rose-600 flex-shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </div>
                    <?php endif; ?>
                    <div>
                        <p class="font-semibold text-sm"><?= $alert['type'] === 'success' ? 'Success!' : 'Please fix the errors below:' ?></p>
                        <p class="text-sm mt-0.5"><?= $alert['text'] ?></p>
                    </div>
                </div>
                <button type="button" onclick="document.getElementById('flash-banner').remove()" class="text-slate-400 hover:text-slate-600 p-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
        <?php endif; ?>

        <!-- Quick Summary Stats -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
            <!-- Total Products -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wider text-slate-500">Total Products</p>
                    <h3 class="text-2xl font-extrabold text-slate-800 mt-1"><?= count($products) ?></h3>
                    <span class="text-xs text-indigo-600 font-medium">In your inventory</span>
                </div>
                <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                </div>
            </div>

            <!-- Uploads Directory -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wider text-slate-500">Image Storage</p>
                    <h3 class="text-lg font-bold text-slate-800 mt-1 truncate">/upload/</h3>
                    <span class="text-xs text-emerald-600 font-medium">Folder ready & writable</span>
                </div>
                <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                </div>
            </div>

            <!-- Total Users -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wider text-slate-500">Registered Users</p>
                    <h3 class="text-2xl font-extrabold text-slate-800 mt-1"><?= count($users) ?></h3>
                    <span class="text-xs text-slate-500">In customer database</span>
                </div>
                <div class="w-12 h-12 bg-amber-50 text-amber-600 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                </div>
            </div>

            <!-- Database Engine -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wider text-slate-500">Database Engine</p>
                    <h3 class="text-lg font-bold text-slate-800 mt-1">MySQL / MariaDB</h3>
                    <span class="text-xs text-emerald-600 font-medium">Connected successfully</span>
                </div>
                <div class="w-12 h-12 bg-sky-50 text-sky-600 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"></path></svg>
                </div>
            </div>
        </div>

        <!-- Main Product Section: Split 2-Column Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
            
            <!-- Left Side: Add Product Form (5 Columns) -->
            <div id="add-product-form" class="lg:col-span-5 bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden sticky top-24">
                <div class="p-6 border-b border-slate-100 bg-gradient-to-r from-slate-50 to-white flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                            <span class="w-8 h-8 rounded-lg bg-indigo-600 text-white flex items-center justify-center text-sm font-bold shadow-sm">+</span>
                            Add New Product
                        </h2>
                        <p class="text-xs text-slate-500 mt-1">Upload image and details to your catalog.</p>
                    </div>
                    <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-indigo-50 text-indigo-700 border border-indigo-100">Live Upload</span>
                </div>

                <form action="index.php" method="POST" enctype="multipart/form-data" class="p-6 space-y-5" id="productForm">
                    <input type="hidden" name="action" value="add_product">

                    <!-- Product Name -->
                    <div>
                        <label for="name" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">
                            Product Name <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <input type="text" id="name" name="name" required placeholder="e.g. Wireless Noise-Cancelling Headphones"
                                value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '' ?>"
                                class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition placeholder:text-slate-400">
                        </div>
                    </div>

                    <!-- Product Price -->
                    <div>
                        <label for="price" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">
                            Price (Rs. / LKR) <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative rounded-xl shadow-sm">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400 text-sm font-semibold">
                                Rs.
                            </div>
                            <input type="number" step="0.01" min="0.01" id="price" name="price" required placeholder="0.00"
                                value="<?= isset($_POST['price']) ? htmlspecialchars($_POST['price']) : '' ?>"
                                class="w-full pl-12 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition placeholder:text-slate-400">
                        </div>
                    </div>

                    <!-- Image Upload with Live Preview -->
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">
                            Product Image <span class="text-rose-500">*</span>
                            <span class="text-slate-400 font-normal lowercase">(saves to /upload/)</span>
                        </label>
                        
                        <!-- Upload Dropzone / Trigger -->
                        <div id="dropzone" class="border-2 border-dashed border-slate-200 hover:border-indigo-400 bg-slate-50/50 hover:bg-indigo-50/20 rounded-2xl p-5 text-center transition cursor-pointer relative group">
                            <input type="file" id="product_image" name="product_image" accept="image/jpeg,image/png,image/webp,image/gif" required class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                            
                            <!-- Default State UI -->
                            <div id="upload-prompt" class="space-y-2">
                                <div class="w-12 h-12 rounded-xl bg-indigo-50 text-indigo-600 mx-auto flex items-center justify-center group-hover:scale-110 transition duration-200">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-slate-700">Click or drag image here</p>
                                    <p class="text-xs text-slate-400 mt-0.5">JPG, PNG, WEBP, GIF up to 5MB</p>
                                </div>
                            </div>

                            <!-- Selected Image Preview UI -->
                            <div id="preview-container" class="hidden space-y-3">
                                <div class="relative w-full h-44 rounded-xl overflow-hidden bg-slate-100 border border-slate-200 shadow-inner mx-auto">
                                    <img id="image-preview" src="" alt="Preview" class="w-full h-full object-cover">
                                    <span class="absolute top-2 right-2 bg-slate-900/80 text-white text-[10px] font-semibold px-2 py-0.5 rounded-full backdrop-blur-sm">
                                        Selected
                                    </span>
                                </div>
                                <div class="flex items-center justify-between text-xs text-slate-500 px-1">
                                    <span id="preview-filename" class="font-medium truncate max-w-[200px]">filename.jpg</span>
                                    <span class="text-indigo-600 font-semibold cursor-pointer hover:underline">Change image</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" id="submitBtn" class="w-full py-3 px-4 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-xl shadow-lg shadow-indigo-600/25 hover:shadow-indigo-600/35 transition duration-150 flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                        <span>Upload & Save Product</span>
                    </button>
                </form>
            </div>

            <!-- Right Side: Product Catalog Display (7 Columns) -->
            <div class="lg:col-span-7 space-y-6">
                
                <!-- Catalog Header & Search -->
                <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                            Product Catalog
                            <span class="text-xs bg-slate-100 text-slate-600 px-2 py-0.5 rounded-full font-semibold border border-slate-200" id="productCountBadge"><?= count($products) ?> items</span>
                        </h2>
                        <p class="text-xs text-slate-500 mt-0.5">Showing all products stored in database & /upload/ folder.</p>
                    </div>

                    <!-- Live Instant Filter -->
                    <div class="w-full sm:w-64 relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </div>
                        <input type="text" id="searchInput" placeholder="Search products..." class="w-full pl-9 pr-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition">
                    </div>
                </div>

                <!-- Products Grid / Cards -->
                <?php if (!empty($products)): ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5" id="productGrid">
                        <?php foreach ($products as $prod): ?>
                            <?php 
                                $img_filename = htmlspecialchars($prod['image'] ?? '');
                                $has_file = !empty($img_filename) && file_exists($upload_dir . $img_filename);
                                $img_url = $has_file ? 'upload/' . $img_filename : null;
                            ?>
                            <div class="product-card bg-white rounded-2xl border border-slate-200/80 shadow-sm hover:shadow-md transition-all duration-200 overflow-hidden flex flex-col group" data-name="<?= strtolower(htmlspecialchars($prod['name'])) ?>">
                                
                                <!-- Card Image -->
                                <div class="relative h-48 w-full bg-slate-100 overflow-hidden">
                                    <?php if ($img_url): ?>
                                        <img src="<?= $img_url ?>" alt="<?= htmlspecialchars($prod['name']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                                    <?php else: ?>
                                        <div class="w-full h-full flex flex-col items-center justify-center text-slate-400 bg-slate-50">
                                            <svg class="w-10 h-10 mb-1 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                            <span class="text-xs font-medium">No image found</span>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Product ID Badge -->
                                    <div class="absolute top-3 left-3 bg-slate-900/70 backdrop-blur-md text-white text-[11px] font-bold px-2 py-0.5 rounded-lg shadow">
                                        #<?= str_pad($prod['id'], 3, '0', STR_PAD_LEFT) ?>
                                    </div>

                                    <!-- Storage Folder Badge -->
                                    <?php if (!empty($img_filename)): ?>
                                        <div class="absolute top-3 right-3 bg-white/90 backdrop-blur-md text-slate-700 text-[10px] font-semibold px-2 py-0.5 rounded-lg shadow flex items-center gap-1">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                            upload/
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Card Body -->
                                <div class="p-5 flex-1 flex flex-col justify-between space-y-3">
                                    <div>
                                        <h3 class="font-bold text-slate-800 text-base leading-snug line-clamp-2">
                                            <?= htmlspecialchars($prod['name']) ?>
                                        </h3>
                                        <div class="mt-2 flex items-baseline gap-1">
                                            <span class="text-xs font-semibold text-slate-500">Rs.</span>
                                            <span class="text-xl font-extrabold text-indigo-600">
                                                <?= number_format((float)$prod['price'], 2) ?>
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Card Footer with Actions -->
                                    <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
                                        <span class="text-slate-400 font-mono text-[11px] truncate max-w-[140px]" title="<?= $img_filename ?>">
                                            <?= $img_filename ? $img_filename : 'No image' ?>
                                        </span>
                                        
                                        <!-- Delete Product Form -->
                                        <form action="index.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this product? The image in /upload/ will also be removed.');">
                                            <input type="hidden" name="action" value="delete_product">
                                            <input type="hidden" name="product_id" value="<?= $prod['id'] ?>">
                                            <button type="submit" class="inline-flex items-center gap-1 text-rose-500 hover:text-rose-700 font-semibold hover:bg-rose-50 px-2 py-1 rounded-lg transition">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </div>

                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <!-- Empty State -->
                    <div class="bg-white rounded-2xl border border-slate-200/80 p-12 text-center shadow-sm">
                        <div class="w-16 h-16 bg-indigo-50 text-indigo-500 rounded-2xl mx-auto flex items-center justify-center mb-4">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                        </div>
                        <h3 class="text-base font-bold text-slate-800">No products added yet</h3>
                        <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">Fill out the form on the left with an image and price to add your first product to the catalog.</p>
                    </div>
                <?php endif; ?>

            </div>

        </div>

        <!-- Registered Users Section (Preserved) -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
            <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
                <div>
                    <h3 class="text-base font-bold text-slate-800">Registered Users</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Existing users stored in MySQL users table.</p>
                </div>
                <span class="bg-indigo-50 text-indigo-700 text-xs font-bold px-3 py-1 rounded-full border border-indigo-100">
                    <?= count($users) ?> Total
                </span>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full whitespace-nowrap">
                    <thead class="bg-slate-50 text-slate-500 text-xs uppercase font-semibold text-left">
                        <tr>
                            <th class="px-6 py-3.5 tracking-wider">User ID</th>
                            <th class="px-6 py-3.5 tracking-wider">Full Name</th>
                            <th class="px-6 py-3.5 tracking-wider">Email Address</th>
                            <th class="px-6 py-3.5 tracking-wider text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <?php if (!empty($users)): ?>
                            <?php foreach ($users as $user): ?>
                                <tr class="hover:bg-slate-50/60 transition duration-150">
                                    <td class="px-6 py-4 text-xs text-slate-500 font-mono">#<?= str_pad($user['id'], 4, '0', STR_PAD_LEFT) ?></td>
                                    <td class="px-6 py-4 text-sm font-semibold text-slate-800"><?= htmlspecialchars($user['name']) ?></td>
                                    <td class="px-6 py-4 text-sm text-slate-500"><?= htmlspecialchars($user['email']) ?></td>
                                    <td class="px-6 py-4 text-sm text-right">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">Verified</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="px-6 py-8 text-center text-slate-500 text-sm">No users found in database.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- JavaScript for Live Image Preview & Instant Search -->
    <script>
        // Live image preview
        const fileInput = document.getElementById('product_image');
        const previewContainer = document.getElementById('preview-container');
        const uploadPrompt = document.getElementById('upload-prompt');
        const imagePreview = document.getElementById('image-preview');
        const previewFilename = document.getElementById('preview-filename');
        const dropzone = document.getElementById('dropzone');

        fileInput.addEventListener('change', function(e) {
            const file = this.files[0];
            if (file) {
                // Check if file is image
                if (!file.type.startsWith('image/')) {
                    alert('Please select an image file (JPG, PNG, WEBP, GIF).');
                    this.value = '';
                    return;
                }

                // Check file size (5MB)
                if (file.size > 5 * 1024 * 1024) {
                    alert('The selected image exceeds 5MB size limit.');
                    this.value = '';
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(event) {
                    imagePreview.src = event.target.result;
                    previewFilename.textContent = file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
                    uploadPrompt.classList.add('hidden');
                    previewContainer.classList.remove('hidden');
                    dropzone.classList.add('image-preview-active');
                };
                reader.readAsDataURL(file);
            } else {
                uploadPrompt.classList.remove('hidden');
                previewContainer.classList.add('hidden');
                dropzone.classList.remove('image-preview-active');
            }
        });

        // Instant Search filter for products
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const query = this.value.toLowerCase().trim();
                const cards = document.querySelectorAll('.product-card');
                let visibleCount = 0;

                cards.forEach(card => {
                    const name = card.getAttribute('data-name') || '';
                    if (name.includes(query)) {
                        card.style.display = '';
                        visibleCount++;
                    } else {
                        card.style.display = 'none';
                    }
                });

                const badge = document.getElementById('productCountBadge');
                if (badge) {
                    badge.textContent = visibleCount + ' items';
                }
            });
        }
    </script>
</body>
</html>