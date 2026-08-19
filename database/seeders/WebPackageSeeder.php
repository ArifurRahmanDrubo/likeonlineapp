<?php

namespace Database\Seeders;

use App\Models\WebPackage;
use Illuminate\Database\Seeder;

class WebPackageSeeder extends Seeder
{
    /**
     * Seed the initial public website packages.
     *
     * Data mirrors the packages previously hard-coded in the public website
     * (likeonlinebd/src/components/Website/Packages/Packages.vue):
     * home packages, corporate packages and upcoming BTRC tariff packages.
     */
    public function run(): void
    {
        $featureBase = [
            'Facebook Speed: 100 Mbps',
            'Youtube Speed: 100 Mbps',
            'FTP Speed: 100 Mbps',
            'IPV6 Is Available',
            '24/7 phone and online support',
            'Real IP: No',
        ];

        $packages = [
            // ---------------------------------------------------------------
            // Home packages
            // ---------------------------------------------------------------
            ['title' => 'Package-1', 'price' => '৳500', 'package_type' => 'home', 'features' => array_merge(['Speed: 10 Mbps'], $featureBase), 'sort_order' => 1],
            ['title' => 'Package-2', 'price' => '৳800', 'package_type' => 'home', 'features' => array_merge(['Speed: 16 Mbps'], $featureBase), 'sort_order' => 2],
            ['title' => 'Package-3', 'price' => '৳1000', 'package_type' => 'home', 'features' => array_merge(['Speed: 20 Mbps'], $featureBase), 'sort_order' => 3],
            ['title' => 'Package-4', 'price' => '৳1200', 'package_type' => 'home', 'features' => array_merge(['Speed: 25 Mbps'], $featureBase), 'sort_order' => 4],
            ['title' => 'Package-5', 'price' => '৳1500', 'package_type' => 'home', 'features' => array_merge(['Speed: 35 Mbps'], $featureBase), 'sort_order' => 5],
            ['title' => 'Package-8', 'price' => '৳1800', 'package_type' => 'home', 'features' => array_merge(['Speed: 40 Mbps'], $featureBase), 'sort_order' => 6],
            ['title' => 'Package-9', 'price' => '৳2000', 'package_type' => 'home', 'features' => array_merge(['Speed: 45 Mbps'], $featureBase), 'sort_order' => 7],
            ['title' => 'Package-10', 'price' => '৳2500', 'package_type' => 'home', 'features' => array_merge(['Speed: 50 Mbps'], $featureBase), 'sort_order' => 8],

            // ---------------------------------------------------------------
            // Corporate packages
            // ---------------------------------------------------------------
            ['title' => 'Package-1', 'price' => '৳500', 'package_type' => 'corporate', 'features' => array_merge(['Speed: 10 Mbps'], $featureBase), 'sort_order' => 1],
            ['title' => 'Package-2', 'price' => '৳800', 'package_type' => 'corporate', 'features' => array_merge(['Speed: 16 Mbps'], $featureBase), 'sort_order' => 2],
            ['title' => 'Package-3', 'price' => '৳1000', 'package_type' => 'corporate', 'features' => array_merge(['Speed: 20 Mbps'], $featureBase), 'sort_order' => 3],
            ['title' => 'Package-4', 'price' => '৳1200', 'package_type' => 'corporate', 'features' => array_merge(['Speed: 25 Mbps'], $featureBase), 'sort_order' => 4],
            ['title' => 'Package-5', 'price' => '৳1500', 'package_type' => 'corporate', 'features' => array_merge(['Speed: 35 Mbps'], $featureBase), 'sort_order' => 5],
            ['title' => 'Package-6', 'price' => '৳1800', 'package_type' => 'corporate', 'features' => array_merge(['Speed: 40 Mbps'], $featureBase), 'sort_order' => 6],
            ['title' => 'Package-7', 'price' => '৳2000', 'package_type' => 'corporate', 'features' => array_merge(['Speed: 45 Mbps'], $featureBase), 'sort_order' => 7],
            ['title' => 'Package-8', 'price' => '৳2500', 'package_type' => 'corporate', 'features' => array_merge(['Speed: 50 Mbps'], $featureBase), 'sort_order' => 8],

            // ---------------------------------------------------------------
            // Upcoming BTRC tariff packages
            // ---------------------------------------------------------------
            [
                'title' => 'Package-1 (BTRC)',
                'price' => '৳525',
                'package_type' => 'upcoming',
                'button_label' => 'Coming Soon',
                'features' => ['Speed: 20 Mbps', 'Bufferless Facebook & YouTube', '100 Mbps FTP Speed', 'IPV6 Is Available', '24/7 Dedicated Support', 'Real IP: No', 'One Country One Rate compliant'],
                'sort_order' => 1,
            ],
            [
                'title' => 'Package-2 (BTRC)',
                'price' => '৳735',
                'package_type' => 'upcoming',
                'button_label' => 'Coming Soon',
                'features' => ['Speed: 30 Mbps', 'Bufferless Facebook & YouTube', '100 Mbps FTP Speed', 'IPV6 Is Available', '24/7 Dedicated Support', 'Real IP: No', 'One Country One Rate compliant'],
                'sort_order' => 2,
            ],
            [
                'title' => 'Package-3 (BTRC)',
                'price' => '৳1050',
                'package_type' => 'upcoming',
                'button_label' => 'Coming Soon',
                'features' => ['Speed: 50 Mbps', 'Bufferless Facebook & YouTube', '100 Mbps FTP Speed', 'IPV6 Is Available', '24/7 Dedicated Support', 'Real IP: No', 'Best for Home & Gaming'],
                'sort_order' => 3,
            ],
            [
                'title' => 'Package-4 (BTRC)',
                'price' => '৳1575',
                'package_type' => 'upcoming',
                'button_label' => 'Coming Soon',
                'features' => ['Speed: 100 Mbps', 'Super Fast FB & YouTube', '100 Mbps FTP Speed', 'IPV6 Is Available', '24/7 Dedicated Support', 'Real IP: Available (Optional)', 'Ideal for Multiple Users'],
                'sort_order' => 4,
            ],
            [
                'title' => 'Package-5 (BTRC)',
                'price' => '৳2100',
                'package_type' => 'upcoming',
                'button_label' => 'Coming Soon',
                'features' => ['Speed: 150 Mbps', 'Ultra-Fast Content Delivery', '100 Mbps FTP Speed', 'IPV6 Is Available', 'Priority Phone Support', 'Real IP: Yes', 'Best for Content Creators'],
                'sort_order' => 5,
            ],
            [
                'title' => 'Package-6 (BTRC)',
                'price' => '৳2625',
                'package_type' => 'upcoming',
                'button_label' => 'Coming Soon',
                'features' => ['Speed: 200 Mbps', 'Ultra-Fast Content Delivery', '100 Mbps FTP Speed', 'IPV6 Is Available', 'Corporate Level Support', 'Real IP: Yes', 'Perfect for Small Offices'],
                'sort_order' => 6,
            ],
            [
                'title' => 'Package-7 (BTRC)',
                'price' => '৳3150',
                'package_type' => 'upcoming',
                'button_label' => 'Coming Soon',
                'features' => ['Speed: 250 Mbps', 'Maximum Bandwidth Capacity', '100 Mbps FTP Speed', 'IPV6 Is Available', '24/7 Executive Support', 'Real IP: Yes', 'Heavy Downloading & Streaming'],
                'sort_order' => 7,
            ],
        ];

        foreach ($packages as $package) {
            // Idempotent: re-running the seeder updates instead of duplicating.
            WebPackage::updateOrCreate(
                ['title' => $package['title'], 'package_type' => $package['package_type']],
                array_merge($package, ['button_label' => $package['button_label'] ?? 'Buy Package', 'status' => true])
            );
        }
    }
}
