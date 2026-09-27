<?php

declare(strict_types=1);

/**
 * One-off CLI script: inserts sample deeds spanning every category and
 * every status, so search/filter can be exercised realistically. Safe to
 * re-run — skips any deed number that already exists.
 *
 * Deliberately does NOT go through DeedController's markReviewed/
 * markReceived actions — it calls RegistrationDetail's model methods
 * directly, which never trigger the "Received" confirmation SMS. Fake
 * mobile numbers are also obviously non-routable (078 0000xxx), so even
 * a later manual "Resend SMS" against one of these can't reach a real
 * person.
 *
 *   php database/seed_mock_deeds.php
 */

require __DIR__ . '/../bootstrap.php';

use App\Core\Database;
use App\Models\Buyer;
use App\Models\Deed;
use App\Models\RegistrationDetail;
use App\Models\Seller;
use App\Services\DeedStatusService;

$db = Database::connection();

$adminId = (int) $db->query("SELECT id FROM users WHERE username = 'admin' LIMIT 1")->fetchColumn();
if (!$adminId) {
    fwrite(STDERR, "No 'admin' user found — run database/seed_admin.php first.\n");
    exit(1);
}

// [deed_no, category, status, buyer_name, buyer_nic, buyer_phone, seller_name, seller_nic, seller_phone, folio, amount, value]
$rows = [
    ['MOCK-TD-001', 'Transfer Deed', 'Submitted', 'Nadeesha Ranasinghe', '199245612378', '0780000001', 'Chaminda Herath', '198734512390', '0780000002', null, '450000.00', '500000.00'],
    ['MOCK-TD-002', 'Transfer Deed', 'Received', 'Ishara Gunasekara', '199612345671', '0780000003', 'Priyantha Wickramasinghe', '197501234567', '0780000004', 'F-1001', '1250000.00', '1400000.00'],
    ['MOCK-LD-001', 'Lease Deed', 'Reviewed', 'Kasun Fernando', '198912345678', '0780000005', 'Anoma Rathnayake', '196812345678', '0780000006', null, '80000.00', '80000.00'],
    ['MOCK-LD-002', 'Lease Deed', 'Received', 'Dilani Peiris', '199512345670', '0780000007', 'Sampath Bandara', '198012345679', '0780000008', 'F-1002', '150000.00', '150000.00'],
    ['MOCK-MD-001', 'Mortgage Deed', 'Submitted', 'Ruwan Jayawardena', '199312345672', '0780000009', 'People\'s Finance Ltd', '000000000V', '0780000010', null, '2200000.00', '2200000.00'],
    ['MOCK-MD-002', 'Mortgage Deed', 'Reviewed', 'Harshani Amarasinghe', '199712345673', '0780000011', 'Lakshan Silva', '198412345674', '0780000012', null, '950000.00', '1000000.00'],
    ['MOCK-GD-001', 'Gift Deed', 'Received', 'Sarath Wijesinghe', '196512345675', '0780000013', 'Malini Wijesinghe', '199012345676', '0780000014', 'F-1003', null, '600000.00'],
    ['MOCK-GD-002', 'Gift Deed', 'Submitted', 'Nimal Karunaratne', '197212345677', '0780000015', 'Amali Karunaratne', '199812345678', '0780000016', null, null, '300000.00'],
    ['MOCK-POA-001', 'Power of Attorney', 'Reviewed', 'Champika de Silva', '198812345679', '0780000017', 'W. A. Somasiri', '195512345670', '0780000018', null, null, null],
    ['MOCK-POA-002', 'Power of Attorney', 'Received', 'Roshan Mendis', '199112345671', '0780000019', 'Geethani Mendis', '196312345672', '0780000020', 'F-1004', null, null],
    ['MOCK-OT-001', 'Other', 'Submitted', 'Thilina Rajapaksha', '199412345673', '0780000021', 'Sanduni Ekanayake', '199912345674', '0780000022', null, '50000.00', '50000.00'],
    ['MOCK-OT-002', 'Other', 'Reviewed', 'Buddhika Senanayake', '198612345675', '0780000023', 'Ayesha Perera', '197812345676', '0780000024', null, '75000.00', '75000.00'],
];

$created = 0;
$skipped = 0;

foreach ($rows as [$deedNo, $category, $status, $buyerName, $buyerNic, $buyerPhone, $sellerName, $sellerNic, $sellerPhone, $folio, $amount, $value]) {
    if (Deed::findByNumber($deedNo)) {
        $skipped++;
        continue;
    }

    $buyerId = Buyer::findOrCreate($buyerName, $buyerNic, $buyerPhone);
    $sellerId = Seller::findOrCreate($sellerName, $sellerNic, $sellerPhone);

    $deedId = Deed::create([
        'deed_number' => $deedNo,
        'category' => $category,
        'buyer_id' => $buyerId,
        'seller_id' => $sellerId,
        'folio_number' => $folio ?? '',
        'amount' => $amount ?? '',
        'value' => $value ?? '',
        'other_document_numbers' => '',
    ], $adminId);

    if ($status === 'Reviewed' || $status === 'Received') {
        RegistrationDetail::markReviewed($deedId, $adminId);
    }
    if ($status === 'Received') {
        RegistrationDetail::markReceived($deedId, $adminId);
        RegistrationDetail::updateFields($deedId, [
            'register_date' => date('Y-m-d', strtotime('-' . random_int(2, 40) . ' days')),
            'day_book_number' => 'DB-' . random_int(100, 999),
            'new_folio_number' => $folio,
            'notes' => '',
        ], $adminId);
    }
    DeedStatusService::recalculateAndSave($deedId, RegistrationDetail::find($deedId));

    $created++;
    echo "Created {$deedNo} ({$category}, {$status})\n";
}

echo "\nDone: {$created} created, {$skipped} already existed.\n";
