<?php

namespace App\Support\Demo;

/**
 * Fixed catalog of 100 DEMO-prefixed sample products for local seeding.
 *
 * Identification is by SKU prefix DEMO- and the exact SKUs listed here.
 * Stock tiers: 60 in-stock, 25 low-stock, 15 out-of-stock (see stockLevel()).
 */
final class DemoProductCatalog
{
    public const SKU_PREFIX = 'DEMO-';

    public const OPENING_REASON = 'Initial DEMO inventory setup';

    /**
     * @return list<array{
     *     sku: string,
     *     name: string,
     *     description: string,
     *     category: string,
     *     category_slug: string,
     *     unit_price: string,
     *     cost_price: string,
     *     reorder_level: int,
     *     opening_stock: int,
     *     stock_tier: 'in_stock'|'low_stock'|'out_of_stock'
     * }>
     */
    public static function definitions(): array
    {
        $definitions = [];

        foreach (self::rawCatalog() as $index => $row) {
            $tier = self::tierForIndex($index);
            [$reorder, $qty] = self::stockForTier($tier, $index);

            $definitions[] = [
                'sku' => $row['sku'],
                'name' => $row['name'],
                'description' => $row['description'],
                'category' => $row['category'],
                'category_slug' => $row['category_slug'],
                'unit_price' => $row['unit_price'],
                'cost_price' => $row['cost_price'],
                'reorder_level' => $reorder,
                'opening_stock' => $qty,
                'stock_tier' => $tier,
            ];
        }

        return $definitions;
    }

    public static function count(): int
    {
        return count(self::rawCatalog());
    }

    /**
     * @return array<string, int>
     */
    public static function categoryDistribution(): array
    {
        $counts = [];

        foreach (self::rawCatalog() as $row) {
            $counts[$row['category']] = ($counts[$row['category']] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @return array{in_stock: int, low_stock: int, out_of_stock: int}
     */
    public static function stockTierDistribution(): array
    {
        $counts = [
            'in_stock' => 0,
            'low_stock' => 0,
            'out_of_stock' => 0,
        ];

        for ($i = 0; $i < self::count(); $i++) {
            $counts[self::tierForIndex($i)]++;
        }

        return $counts;
    }

    /**
     * @return list<string>
     */
    public static function skus(): array
    {
        return array_column(self::rawCatalog(), 'sku');
    }

    /**
     * @return 'in_stock'|'low_stock'|'out_of_stock'
     */
    protected static function tierForIndex(int $index): string
    {
        // Deterministic: first 60 healthy, next 25 low, last 15 out of stock.
        if ($index < 60) {
            return 'in_stock';
        }

        if ($index < 85) {
            return 'low_stock';
        }

        return 'out_of_stock';
    }

    /**
     * @param  'in_stock'|'low_stock'|'out_of_stock'  $tier
     * @return array{0: int, 1: int} reorder_level, opening_stock
     */
    protected static function stockForTier(string $tier, int $index): array
    {
        return match ($tier) {
            'in_stock' => [
                5 + ($index % 10),
                20 + (($index * 7) % 131),
            ],
            'low_stock' => [
                8,
                1 + ($index % 8),
            ],
            'out_of_stock' => [
                5 + ($index % 6),
                0,
            ],
        };
    }

    /**
     * @return list<array{
     *     sku: string,
     *     name: string,
     *     description: string,
     *     category: string,
     *     category_slug: string,
     *     unit_price: string,
     *     cost_price: string
     * }>
     */
    protected static function rawCatalog(): array
    {
        return [
            // Computer Accessories — 20
            self::row('DEMO-ACC-001', 'Ergonomic Wireless Mouse', 'Ambidextrous wireless mouse with silent clicks for daily office use.', 'Computer Accessories', 'computer-accessories', '34.99', '18.50'),
            self::row('DEMO-ACC-002', 'Mechanical Keyboard', 'Tactile switch keyboard with detachable USB-C cable.', 'Computer Accessories', 'computer-accessories', '89.00', '48.00'),
            self::row('DEMO-ACC-003', 'USB-C Docking Station', 'Multi-port dock with dual display, ethernet, and power delivery.', 'Computer Accessories', 'computer-accessories', '149.00', '82.00'),
            self::row('DEMO-ACC-004', 'Laptop Cooling Pad', 'Quiet dual-fan cooling pad sized for 15-inch notebooks.', 'Computer Accessories', 'computer-accessories', '29.50', '14.25'),
            self::row('DEMO-ACC-005', 'Adjustable Laptop Stand', 'Aluminum riser with height and angle adjustment.', 'Computer Accessories', 'computer-accessories', '42.00', '21.00'),
            self::row('DEMO-ACC-006', 'Wireless Presenter Remote', 'Slide clicker with laser pointer for conference rooms.', 'Computer Accessories', 'computer-accessories', '24.99', '11.50'),
            self::row('DEMO-ACC-007', 'Compact Trackball Mouse', 'Thumb-operated trackball for limited desk space.', 'Computer Accessories', 'computer-accessories', '54.00', '29.00'),
            self::row('DEMO-ACC-008', 'Numeric Keypad', 'USB numeric keypad for spreadsheet work.', 'Computer Accessories', 'computer-accessories', '19.99', '9.25'),
            self::row('DEMO-ACC-009', 'Monitor Desk Mount', 'Single-arm clamp mount for up to 32-inch monitors.', 'Computer Accessories', 'computer-accessories', '69.00', '36.00'),
            self::row('DEMO-ACC-010', 'Webcam Privacy Cover Set', 'Sliding covers sized for common laptop and monitor cameras.', 'Computer Accessories', 'computer-accessories', '8.99', '3.40'),
            self::row('DEMO-ACC-011', 'Portable USB Hub', '4-port USB 3 hub for travel and hot-desking.', 'Computer Accessories', 'computer-accessories', '22.50', '10.80'),
            self::row('DEMO-ACC-012', 'Gel Wrist Rest Kit', 'Matching keyboard and mouse wrist rests.', 'Computer Accessories', 'computer-accessories', '18.00', '7.90'),
            self::row('DEMO-ACC-013', 'Vertical Ergonomic Mouse', 'Handshake-grip mouse to reduce forearm strain.', 'Computer Accessories', 'computer-accessories', '47.00', '24.50'),
            self::row('DEMO-ACC-014', 'Laptop Sleeve 15 Inch', 'Padded neoprene sleeve with accessory pocket.', 'Computer Accessories', 'computer-accessories', '27.00', '12.00'),
            self::row('DEMO-ACC-015', 'Bluetooth Mouse', 'Compact Bluetooth mouse with multi-device pairing.', 'Computer Accessories', 'computer-accessories', '31.00', '15.75'),
            self::row('DEMO-ACC-016', 'Keyboard Cover Film', 'Dust cover sized for full-size office keyboards.', 'Computer Accessories', 'computer-accessories', '9.50', '3.80'),
            self::row('DEMO-ACC-017', 'USB Desk Fan', 'Quiet clip-on USB fan for personal cooling.', 'Computer Accessories', 'computer-accessories', '16.99', '7.20'),
            self::row('DEMO-ACC-018', 'Monitor Light Bar', 'Screen-mounted LED bar with adjustable brightness.', 'Computer Accessories', 'computer-accessories', '58.00', '30.00'),
            self::row('DEMO-ACC-019', 'Wireless Charging Mouse Pad', 'Qi-compatible pad with microfiber surface.', 'Computer Accessories', 'computer-accessories', '39.00', '19.50'),
            self::row('DEMO-ACC-020', 'Laptop Privacy Screen', 'Clip-on privacy filter for 14-inch displays.', 'Computer Accessories', 'computer-accessories', '44.00', '22.00'),

            // Networking Equipment — 15
            self::row('DEMO-NET-001', 'Gigabit Ethernet Switch', '8-port unmanaged switch for small office networks.', 'Networking Equipment', 'networking-equipment', '59.00', '31.00'),
            self::row('DEMO-NET-002', 'Dual-Band Wi-Fi Router', 'AX dual-band router suitable for open-plan offices.', 'Networking Equipment', 'networking-equipment', '129.00', '72.00'),
            self::row('DEMO-NET-003', 'Wireless Access Point', 'Ceiling-mount AP for conference room coverage.', 'Networking Equipment', 'networking-equipment', '119.00', '65.00'),
            self::row('DEMO-NET-004', 'Network Patch Panel', '24-port Cat6 patch panel for rack installation.', 'Networking Equipment', 'networking-equipment', '74.00', '38.00'),
            self::row('DEMO-NET-005', 'USB Wi-Fi Adapter', 'USB-A dual-band adapter for desktop PCs.', 'Networking Equipment', 'networking-equipment', '28.00', '13.50'),
            self::row('DEMO-NET-006', 'PoE Injector', 'Single-port gigabit PoE injector for cameras and APs.', 'Networking Equipment', 'networking-equipment', '36.00', '18.00'),
            self::row('DEMO-NET-007', 'Network Cable Tester', 'Handheld continuity tester for RJ45 runs.', 'Networking Equipment', 'networking-equipment', '32.00', '15.00'),
            self::row('DEMO-NET-008', 'Mesh Wi-Fi Node', 'Add-on mesh node for extended floor coverage.', 'Networking Equipment', 'networking-equipment', '99.00', '54.00'),
            self::row('DEMO-NET-009', 'Gigabit Media Converter', 'Fiber-to-copper converter for building links.', 'Networking Equipment', 'networking-equipment', '84.00', '45.00'),
            self::row('DEMO-NET-010', 'Rackmount PDU Strip', '1U power distribution strip for network racks.', 'Networking Equipment', 'networking-equipment', '67.00', '34.00'),
            self::row('DEMO-NET-011', 'VPN Firewall Appliance', 'Desktop firewall for branch office VPN tunnels.', 'Networking Equipment', 'networking-equipment', '249.00', '140.00'),
            self::row('DEMO-NET-012', 'Managed 16-Port Switch', 'Layer-2 managed switch with VLAN support.', 'Networking Equipment', 'networking-equipment', '189.00', '105.00'),
            self::row('DEMO-NET-013', 'Wi-Fi Range Extender', 'Wall-plug extender for dead-zone coverage.', 'Networking Equipment', 'networking-equipment', '45.00', '22.00'),
            self::row('DEMO-NET-014', 'RJ45 Crimp Tool Kit', 'Crimper, stripper, and connector kit for field work.', 'Networking Equipment', 'networking-equipment', '29.00', '13.00'),
            self::row('DEMO-NET-015', 'Network Keystone Jack Pack', 'Pack of 50 Cat6 keystone jacks.', 'Networking Equipment', 'networking-equipment', '41.00', '19.00'),

            // Office Supplies — 15
            self::row('DEMO-OFF-001', 'A4 Copy Paper', 'Ream of 500 sheets multipurpose A4 paper.', 'Office Supplies', 'office-supplies', '8.50', '4.20'),
            self::row('DEMO-OFF-002', 'Heavy-Duty Stapler', 'Desktop stapler for up to 40 sheets.', 'Office Supplies', 'office-supplies', '14.00', '6.50'),
            self::row('DEMO-OFF-003', 'Whiteboard Marker Set', 'Assorted-color dry-erase markers with eraser.', 'Office Supplies', 'office-supplies', '12.99', '5.80'),
            self::row('DEMO-OFF-004', 'Desk Organizer', 'Multi-compartment organizer for pens and clips.', 'Office Supplies', 'office-supplies', '17.50', '8.00'),
            self::row('DEMO-OFF-005', 'Document Shredder', 'Cross-cut shredder for desk-side paper disposal.', 'Office Supplies', 'office-supplies', '79.00', '42.00'),
            self::row('DEMO-OFF-006', 'Sticky Note Assortment', 'Mixed sizes and colors for meeting notes.', 'Office Supplies', 'office-supplies', '9.99', '4.50'),
            self::row('DEMO-OFF-007', 'Binder Clip Assortment', 'Assorted binder clips for file bundles.', 'Office Supplies', 'office-supplies', '6.50', '2.70'),
            self::row('DEMO-OFF-008', 'Letter Tray Set', 'Stackable dual letter trays.', 'Office Supplies', 'office-supplies', '21.00', '10.00'),
            self::row('DEMO-OFF-009', 'Ballpoint Pen Box', 'Box of 50 medium-point ballpoint pens.', 'Office Supplies', 'office-supplies', '11.00', '5.00'),
            self::row('DEMO-OFF-010', 'Desktop Calculator', '12-digit desktop calculator with large keys.', 'Office Supplies', 'office-supplies', '15.00', '7.00'),
            self::row('DEMO-OFF-011', 'Filing Folder Pack', 'Pack of 100 manila filing folders.', 'Office Supplies', 'office-supplies', '18.00', '8.50'),
            self::row('DEMO-OFF-012', 'Packing Tape Dispenser', 'Handheld dispenser with two tape rolls.', 'Office Supplies', 'office-supplies', '13.50', '6.00'),
            self::row('DEMO-OFF-013', 'Whiteboard Cleaning Spray', 'Board cleaner spray with microfiber cloth.', 'Office Supplies', 'office-supplies', '10.00', '4.40'),
            self::row('DEMO-OFF-014', 'Name Badge Holder Pack', 'Pack of 50 horizontal badge holders.', 'Office Supplies', 'office-supplies', '16.00', '7.50'),
            self::row('DEMO-OFF-015', 'Desk Calendar Pad', 'Daily tear-off calendar pad for the current year.', 'Office Supplies', 'office-supplies', '7.99', '3.60'),

            // Storage Devices — 15
            self::row('DEMO-STG-001', '1TB External SSD', 'Portable USB-C solid-state drive for backups.', 'Storage Devices', 'storage-devices', '119.00', '68.00'),
            self::row('DEMO-STG-002', '256GB USB Flash Drive', 'Metal USB-C flash drive for file transfer.', 'Storage Devices', 'storage-devices', '24.00', '11.00'),
            self::row('DEMO-STG-003', '2TB External Hard Drive', 'USB desktop drive for archive storage.', 'Storage Devices', 'storage-devices', '89.00', '49.00'),
            self::row('DEMO-STG-004', 'SDXC Memory Card', '128GB UHS-I card for cameras and readers.', 'Storage Devices', 'storage-devices', '22.00', '10.50'),
            self::row('DEMO-STG-005', 'NVMe SSD Enclosure', 'USB-C enclosure for M.2 NVMe modules.', 'Storage Devices', 'storage-devices', '35.00', '17.00'),
            self::row('DEMO-STG-006', '512GB Internal SSD', '2.5-inch SATA SSD for workstation upgrades.', 'Storage Devices', 'storage-devices', '54.00', '29.00'),
            self::row('DEMO-STG-007', 'MicroSD Adapter Kit', 'MicroSD cards with full-size adapters.', 'Storage Devices', 'storage-devices', '18.00', '8.00'),
            self::row('DEMO-STG-008', 'RAID Docking Station', 'Dual-bay USB dock for drive cloning.', 'Storage Devices', 'storage-devices', '99.00', '55.00'),
            self::row('DEMO-STG-009', '64GB USB Flash Drive Pack', 'Pack of five 64GB USB-A drives.', 'Storage Devices', 'storage-devices', '39.00', '18.00'),
            self::row('DEMO-STG-010', '1TB NVMe Upgrade Module', 'M.2 NVMe module for laptop upgrades.', 'Storage Devices', 'storage-devices', '79.00', '44.00'),
            self::row('DEMO-STG-011', 'Optical Disc Spindle', 'Spindle of 50 printable DVD-R discs.', 'Storage Devices', 'storage-devices', '19.00', '9.00'),
            self::row('DEMO-STG-012', 'Card Reader Hub', 'Multi-slot SD and microSD reader with USB hub.', 'Storage Devices', 'storage-devices', '27.00', '12.50'),
            self::row('DEMO-STG-013', 'Encrypted USB Drive', 'Hardware-encrypted 128GB USB drive.', 'Storage Devices', 'storage-devices', '64.00', '34.00'),
            self::row('DEMO-STG-014', '4TB Backup Drive', 'High-capacity USB drive for nightly backups.', 'Storage Devices', 'storage-devices', '129.00', '74.00'),
            self::row('DEMO-STG-015', 'SSD Thermal Pad Kit', 'Thermal pads for NVMe heatsink installs.', 'Storage Devices', 'storage-devices', '12.00', '5.00'),

            // Audio & Video — 10
            self::row('DEMO-AV-001', 'USB Conference Microphone', 'Omnidirectional mic for hybrid meeting rooms.', 'Audio & Video', 'audio-video', '79.00', '42.00'),
            self::row('DEMO-AV-002', 'Stereo Headset', 'Wired stereo headset with boom microphone.', 'Audio & Video', 'audio-video', '36.00', '17.00'),
            self::row('DEMO-AV-003', '1080p Webcam', 'Full-HD webcam with privacy shutter.', 'Audio & Video', 'audio-video', '49.00', '25.00'),
            self::row('DEMO-AV-004', 'Desktop Speakers', 'USB-powered stereo speakers for desks.', 'Audio & Video', 'audio-video', '32.00', '15.00'),
            self::row('DEMO-AV-005', 'HDMI Capture Adapter', 'USB capture dongle for recording HDMI sources.', 'Audio & Video', 'audio-video', '55.00', '28.00'),
            self::row('DEMO-AV-006', 'Wireless Headset', 'Dual-ear wireless headset for call centers.', 'Audio & Video', 'audio-video', '89.00', '48.00'),
            self::row('DEMO-AV-007', 'Lavalier Microphone', 'Clip-on USB lavalier mic for presentations.', 'Audio & Video', 'audio-video', '28.00', '13.00'),
            self::row('DEMO-AV-008', 'Portable Bluetooth Speaker', 'Compact speaker for huddle rooms.', 'Audio & Video', 'audio-video', '41.00', '20.00'),
            self::row('DEMO-AV-009', 'Ring Light Kit', 'Desk ring light with phone mount for video calls.', 'Audio & Video', 'audio-video', '38.00', '18.00'),
            self::row('DEMO-AV-010', 'HDMI Switch 3-Port', 'Three-in one-out HDMI switch for shared displays.', 'Audio & Video', 'audio-video', '26.00', '12.00'),

            // Cables & Adapters — 10
            self::row('DEMO-CAB-001', 'USB-C Charging Cable', '2-meter USB-C to USB-C charge and sync cable.', 'Cables & Adapters', 'cables-adapters', '14.00', '5.50'),
            self::row('DEMO-CAB-002', 'HDMI 2.1 Cable', '2-meter high-speed HDMI cable for 4K displays.', 'Cables & Adapters', 'cables-adapters', '18.00', '7.00'),
            self::row('DEMO-CAB-003', 'Cat6 Ethernet Cable', '3-meter snagless Cat6 patch cable.', 'Cables & Adapters', 'cables-adapters', '9.50', '3.80'),
            self::row('DEMO-CAB-004', 'USB-C to HDMI Adapter', 'Single-port adapter for external monitors.', 'Cables & Adapters', 'cables-adapters', '22.00', '10.00'),
            self::row('DEMO-CAB-005', 'DisplayPort Cable', '2-meter DisplayPort cable for workstation monitors.', 'Cables & Adapters', 'cables-adapters', '16.00', '6.50'),
            self::row('DEMO-CAB-006', 'USB-A to USB-C Cable', '1-meter cable for legacy host ports.', 'Cables & Adapters', 'cables-adapters', '8.00', '3.00'),
            self::row('DEMO-CAB-007', 'Lightning to USB-C Cable', 'Charge cable for older accessory docks.', 'Cables & Adapters', 'cables-adapters', '15.00', '6.00'),
            self::row('DEMO-CAB-008', 'VGA to HDMI Converter', 'Active converter for legacy projectors.', 'Cables & Adapters', 'cables-adapters', '27.00', '12.50'),
            self::row('DEMO-CAB-009', 'Ethernet Coupler Pack', 'Pack of 10 inline RJ45 couplers.', 'Cables & Adapters', 'cables-adapters', '11.00', '4.20'),
            self::row('DEMO-CAB-010', 'USB Extension Cable', '2-meter USB 3 extension for rear ports.', 'Cables & Adapters', 'cables-adapters', '10.00', '4.00'),

            // Printing Supplies — 10
            self::row('DEMO-PRT-001', 'Black Toner Cartridge', 'Standard-yield black toner for office lasers.', 'Printing Supplies', 'printing-supplies', '64.00', '34.00'),
            self::row('DEMO-PRT-002', 'Cyan Ink Cartridge', 'Cyan ink cartridge for color inkjet printers.', 'Printing Supplies', 'printing-supplies', '28.00', '13.00'),
            self::row('DEMO-PRT-003', 'Thermal Label Roll', 'Continuous thermal label roll for shipping printers.', 'Printing Supplies', 'printing-supplies', '19.00', '8.50'),
            self::row('DEMO-PRT-004', 'Printer Maintenance Kit', 'Pickup rollers and separation pad kit.', 'Printing Supplies', 'printing-supplies', '72.00', '38.00'),
            self::row('DEMO-PRT-005', 'Photo Paper Pack', 'Pack of 50 glossy A4 photo sheets.', 'Printing Supplies', 'printing-supplies', '16.00', '7.00'),
            self::row('DEMO-PRT-006', 'Magenta Ink Cartridge', 'Magenta ink cartridge for color inkjet printers.', 'Printing Supplies', 'printing-supplies', '28.00', '13.00'),
            self::row('DEMO-PRT-007', 'Yellow Ink Cartridge', 'Yellow ink cartridge for color inkjet printers.', 'Printing Supplies', 'printing-supplies', '28.00', '13.00'),
            self::row('DEMO-PRT-008', 'Drum Unit Compatible', 'Imaging drum unit for common laser models.', 'Printing Supplies', 'printing-supplies', '89.00', '48.00'),
            self::row('DEMO-PRT-009', 'Label Sheet Pack', 'Pack of 25 letter-size shipping label sheets.', 'Printing Supplies', 'printing-supplies', '14.00', '6.00'),
            self::row('DEMO-PRT-010', 'Printer Cleaning Kit', 'Swabs and solution for print-head maintenance.', 'Printing Supplies', 'printing-supplies', '12.00', '5.00'),

            // Power & Charging — 5
            self::row('DEMO-PWR-001', '65W USB-C Charger', 'GaN wall charger with USB-C power delivery.', 'Power & Charging', 'power-charging', '39.00', '18.00'),
            self::row('DEMO-PWR-002', 'Surge Protector', '6-outlet surge strip with USB-A ports.', 'Power & Charging', 'power-charging', '29.00', '13.50'),
            self::row('DEMO-PWR-003', 'Laptop Power Adapter', 'Universal 90W tip-kit adapter for notebooks.', 'Power & Charging', 'power-charging', '45.00', '22.00'),
            self::row('DEMO-PWR-004', 'USB Charging Hub', '10-port USB charging station for meeting rooms.', 'Power & Charging', 'power-charging', '54.00', '28.00'),
            self::row('DEMO-PWR-005', 'UPS Battery Backup', '600VA line-interactive UPS for workstations.', 'Power & Charging', 'power-charging', '99.00', '55.00'),
        ];
    }

    /**
     * @return array{
     *     sku: string,
     *     name: string,
     *     description: string,
     *     category: string,
     *     category_slug: string,
     *     unit_price: string,
     *     cost_price: string
     * }
     */
    protected static function row(
        string $sku,
        string $name,
        string $description,
        string $category,
        string $categorySlug,
        string $unitPrice,
        string $costPrice,
    ): array {
        return [
            'sku' => $sku,
            'name' => $name,
            'description' => $description,
            'category' => $category,
            'category_slug' => $categorySlug,
            'unit_price' => $unitPrice,
            'cost_price' => $costPrice,
        ];
    }
}
