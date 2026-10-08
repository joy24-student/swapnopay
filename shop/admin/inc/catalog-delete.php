<?php
/**
 * Safe & Cascading Catalog Entry Deletion Engine
 * Supports clean cascade deletion of Top Categories, Mid Categories, End Categories, Colors, and Sizes.
 * Safely unlinks orphaned photos, cleans up relational tables, and clears menu & product caches.
 */

function deleteStoreCatalogEntry(PDO $pdo, string $kind, int $id): void {
    if ($id < 1) throw new RuntimeException('Choose a valid record.');

    $validKinds = ['top-category', 'mid-category', 'end-category', 'size', 'color'];
    if (!in_array($kind, $validKinds, true)) throw new RuntimeException('Invalid catalog item kind.');

    $uploadsDir = dirname(__DIR__, 2) . '/assets/uploads/';

    // Helper to delete product photos and child associations
    $deleteProducts = static function(array $productIds) use ($pdo, $uploadsDir): void {
        if (empty($productIds)) return;
        $inPlaceholders = implode(',', array_fill(0, count($productIds), '?'));

        // 1. Fetch featured photos to unlink
        $stmt = $pdo->prepare("SELECT p_id, p_featured_photo FROM tbl_product WHERE p_id IN ($inPlaceholders)");
        $stmt->execute($productIds);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($products as $p) {
            if (!empty($p['p_featured_photo']) && is_file($uploadsDir . $p['p_featured_photo'])) {
                @unlink($uploadsDir . $p['p_featured_photo']);
            }
        }

        // 2. Fetch gallery photos to unlink
        $stmt = $pdo->prepare("SELECT photo FROM tbl_product_photo WHERE p_id IN ($inPlaceholders)");
        $stmt->execute($productIds);
        $galleryPhotos = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        foreach ($galleryPhotos as $gPhoto) {
            if (!empty($gPhoto) && is_file($uploadsDir . 'product_photos/' . $gPhoto)) {
                @unlink($uploadsDir . 'product_photos/' . $gPhoto);
            }
        }

        // 3. Delete dependent relations
        $pdo->prepare("DELETE FROM tbl_product_photo WHERE p_id IN ($inPlaceholders)")->execute($productIds);
        $pdo->prepare("DELETE FROM tbl_product_size WHERE p_id IN ($inPlaceholders)")->execute($productIds);
        $pdo->prepare("DELETE FROM tbl_product_color WHERE p_id IN ($inPlaceholders)")->execute($productIds);
        $pdo->prepare("DELETE FROM tbl_rating WHERE p_id IN ($inPlaceholders)")->execute($productIds);
        
        // 4. Clean up order line items referencing deleted products
        try {
            $pdo->prepare("DELETE FROM tbl_order WHERE product_id IN ($inPlaceholders)")->execute($productIds);
        } catch (Throwable $_) {}

        // 5. Delete products
        $pdo->prepare("DELETE FROM tbl_product WHERE p_id IN ($inPlaceholders)")->execute($productIds);
    };

    try {
        $pdo->beginTransaction();

        switch ($kind) {
            case 'top-category':
                // Check record exists
                $stmt = $pdo->prepare("SELECT tcat_id, photo FROM tbl_top_category WHERE tcat_id=? FOR UPDATE");
                $stmt->execute([$id]);
                $topCat = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$topCat) throw new RuntimeException('Top category not found.');

                // Find all mid categories under this top category
                $stmt = $pdo->prepare("SELECT mcat_id FROM tbl_mid_category WHERE tcat_id=?");
                $stmt->execute([$id]);
                $midIds = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

                if (!empty($midIds)) {
                    $inMids = implode(',', array_fill(0, count($midIds), '?'));
                    // Find all end categories under these mid categories
                    $stmt = $pdo->prepare("SELECT ecat_id FROM tbl_end_category WHERE mcat_id IN ($inMids)");
                    $stmt->execute($midIds);
                    $endIds = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

                    if (!empty($endIds)) {
                        $inEnds = implode(',', array_fill(0, count($endIds), '?'));
                        // Find all products under these end categories
                        $stmt = $pdo->prepare("SELECT p_id FROM tbl_product WHERE ecat_id IN ($inEnds)");
                        $stmt->execute($endIds);
                        $productIds = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

                        // Delete products & photos
                        $deleteProducts($productIds);

                        // Delete end categories
                        $pdo->prepare("DELETE FROM tbl_end_category WHERE ecat_id IN ($inEnds)")->execute($endIds);
                    }

                    // Delete mid categories
                    $pdo->prepare("DELETE FROM tbl_mid_category WHERE tcat_id=?")->execute([$id]);
                }

                // Delete top category photo if exists
                if (!empty($topCat['photo']) && is_file($uploadsDir . $topCat['photo'])) {
                    @unlink($uploadsDir . $topCat['photo']);
                }

                // Delete top category
                $pdo->prepare("DELETE FROM tbl_top_category WHERE tcat_id=?")->execute([$id]);
                break;

            case 'mid-category':
                $stmt = $pdo->prepare("SELECT mcat_id FROM tbl_mid_category WHERE mcat_id=? FOR UPDATE");
                $stmt->execute([$id]);
                if (!$stmt->fetchColumn()) throw new RuntimeException('Mid category not found.');

                // Find end categories
                $stmt = $pdo->prepare("SELECT ecat_id FROM tbl_end_category WHERE mcat_id=?");
                $stmt->execute([$id]);
                $endIds = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

                if (!empty($endIds)) {
                    $inEnds = implode(',', array_fill(0, count($endIds), '?'));
                    $stmt = $pdo->prepare("SELECT p_id FROM tbl_product WHERE ecat_id IN ($inEnds)");
                    $stmt->execute($endIds);
                    $productIds = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

                    $deleteProducts($productIds);
                    $pdo->prepare("DELETE FROM tbl_end_category WHERE ecat_id IN ($inEnds)")->execute($endIds);
                }

                $pdo->prepare("DELETE FROM tbl_mid_category WHERE mcat_id=?")->execute([$id]);
                break;

            case 'end-category':
                $stmt = $pdo->prepare("SELECT ecat_id FROM tbl_end_category WHERE ecat_id=? FOR UPDATE");
                $stmt->execute([$id]);
                if (!$stmt->fetchColumn()) throw new RuntimeException('End category not found.');

                $stmt = $pdo->prepare("SELECT p_id FROM tbl_product WHERE ecat_id=?");
                $stmt->execute([$id]);
                $productIds = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

                $deleteProducts($productIds);
                $pdo->prepare("DELETE FROM tbl_end_category WHERE ecat_id=?")->execute([$id]);
                break;

            case 'size':
                $stmt = $pdo->prepare("SELECT size_id FROM tbl_size WHERE size_id=? FOR UPDATE");
                $stmt->execute([$id]);
                if (!$stmt->fetchColumn()) throw new RuntimeException('Size not found.');

                $pdo->prepare("DELETE FROM tbl_product_size WHERE size_id=?")->execute([$id]);
                $pdo->prepare("DELETE FROM tbl_size WHERE size_id=?")->execute([$id]);
                break;

            case 'color':
                $stmt = $pdo->prepare("SELECT color_id FROM tbl_color WHERE color_id=? FOR UPDATE");
                $stmt->execute([$id]);
                if (!$stmt->fetchColumn()) throw new RuntimeException('Color not found.');

                $pdo->prepare("DELETE FROM tbl_product_color WHERE color_id=?")->execute([$id]);
                $pdo->prepare("DELETE FROM tbl_color WHERE color_id=?")->execute([$id]);
                break;
        }

        $pdo->commit();

        if (function_exists('clearShopCache')) {
            clearShopCache('menu');
            clearShopCache('products');
            clearShopCache('all');
        }
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}
