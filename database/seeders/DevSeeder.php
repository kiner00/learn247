<?php

namespace Database\Seeders;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DevSeeder extends Seeder
{
    // ── Real-looking community data ────────────────────────────────────────────
    private array $communities = [
        // Entrepreneurship & Business
        ['name' => 'Startup Founders PH',        'category' => 'Business', 'price' => 999,  'private' => true,  'img' => 'startup'],
        ['name' => 'E-Commerce Mastery',          'category' => 'Business',         'price' => 799,  'private' => true,  'img' => 'business'],
        ['name' => 'Dropshipping Academy PH',     'category' => 'Business',         'price' => 499,  'private' => true,  'img' => 'shipping'],
        ['name' => 'Digital Entrepreneurs Hub',   'category' => 'Business', 'price' => 0,    'private' => false, 'img' => 'technology'],
        ['name' => 'SME Growth Circle',           'category' => 'Business',         'price' => 599,  'private' => true,  'img' => 'office'],
        ['name' => 'Amazon FBA Philippines',      'category' => 'Business',       'price' => 699,  'private' => true,  'img' => 'warehouse'],
        ['name' => 'Franchise Network PH',        'category' => 'Business',         'price' => 999,  'private' => true,  'img' => 'restaurant'],
        ['name' => 'Business Scaling Secrets',    'category' => 'Business', 'price' => 1499, 'private' => true,  'img' => 'growth'],

        // Finance & Investing
        ['name' => 'Stock Market Pinoys',         'category' => 'Finance',          'price' => 599,  'private' => true,  'img' => 'finance'],
        ['name' => 'Crypto Traders PH',           'category' => 'Finance',           'price' => 799,  'private' => true,  'img' => 'crypto'],
        ['name' => 'Real Estate Investors Club',  'category' => 'Finance',      'price' => 1299, 'private' => true,  'img' => 'realestate'],
        ['name' => 'Personal Finance Pilipinas',  'category' => 'Finance',          'price' => 0,    'private' => false, 'img' => 'money'],
        ['name' => 'Forex Trading Academy',       'category' => 'Finance',          'price' => 999,  'private' => true,  'img' => 'trading'],
        ['name' => 'Passive Income Builders',     'category' => 'Finance',          'price' => 499,  'private' => true,  'img' => 'income'],
        ['name' => 'BDO Wealth Hackers',          'category' => 'Finance',        'price' => 299,  'private' => false, 'img' => 'investment'],
        ['name' => 'UITF & ETF Philippines',      'category' => 'Finance',        'price' => 0,    'private' => false, 'img' => 'chart'],

        // Digital Marketing
        ['name' => 'Facebook Ads Mastery PH',     'category' => 'Business',        'price' => 799,  'private' => true,  'img' => 'social'],
        ['name' => 'SEO Philippines Community',   'category' => 'Business',        'price' => 499,  'private' => true,  'img' => 'seo'],
        ['name' => 'Content Creator Academy',     'category' => 'Business',          'price' => 599,  'private' => true,  'img' => 'content'],
        ['name' => 'TikTok Marketing PH',         'category' => 'Business',        'price' => 399,  'private' => false, 'img' => 'video'],
        ['name' => 'Email Marketing Pros',        'category' => 'Business',        'price' => 499,  'private' => true,  'img' => 'email'],
        ['name' => 'Influencer Growth Lab',       'category' => 'Business',     'price' => 699,  'private' => true,  'img' => 'influencer'],
        ['name' => 'Copywriting Collective PH',   'category' => 'Business',        'price' => 599,  'private' => true,  'img' => 'writing'],
        ['name' => 'Brand Building Bootcamp',     'category' => 'Business',        'price' => 999,  'private' => true,  'img' => 'branding'],

        // Technology & Coding
        ['name' => 'Pinoy Developers Network',    'category' => 'Tech',       'price' => 0,    'private' => false, 'img' => 'coding'],
        ['name' => 'Laravel Philippines',         'category' => 'Tech',          'price' => 499,  'private' => true,  'img' => 'php'],
        ['name' => 'AI & Machine Learning PH',    'category' => 'Tech',       'price' => 799,  'private' => true,  'img' => 'ai'],
        ['name' => 'Mobile Dev Circle',           'category' => 'Tech',       'price' => 599,  'private' => true,  'img' => 'mobile'],
        ['name' => 'No-Code Builders PH',         'category' => 'Tech',       'price' => 399,  'private' => false, 'img' => 'nocode'],
        ['name' => 'Cybersecurity Philippines',   'category' => 'Tech',       'price' => 699,  'private' => true,  'img' => 'security'],
        ['name' => 'Data Science PH',             'category' => 'Tech',       'price' => 799,  'private' => true,  'img' => 'data'],
        ['name' => 'Cloud Computing Circle',      'category' => 'Tech',       'price' => 599,  'private' => true,  'img' => 'cloud'],

        // Health & Fitness
        ['name' => 'Pinoy Fitness Nation',        'category' => 'Health',          'price' => 0,    'private' => false, 'img' => 'fitness'],
        ['name' => 'Home Workout Heroes',         'category' => 'Health',          'price' => 299,  'private' => false, 'img' => 'workout'],
        ['name' => 'Keto Diet Philippines',       'category' => 'Health',        'price' => 399,  'private' => true,  'img' => 'food'],
        ['name' => 'Mental Health Warriors PH',   'category' => 'Health',         'price' => 0,    'private' => false, 'img' => 'wellness'],
        ['name' => 'Yoga & Mindfulness PH',       'category' => 'Health',         'price' => 299,  'private' => false, 'img' => 'yoga'],
        ['name' => 'Bodybuilding Philippines',    'category' => 'Health',          'price' => 499,  'private' => true,  'img' => 'gym'],
        ['name' => 'Running Community PH',        'category' => 'Health',          'price' => 0,    'private' => false, 'img' => 'running'],
        ['name' => 'Intermittent Fasting PH',     'category' => 'Health',        'price' => 299,  'private' => false, 'img' => 'health'],

        // Education & Learning
        ['name' => 'Online Teaching Philippines', 'category' => 'Education',        'price' => 499,  'private' => true,  'img' => 'teaching'],
        ['name' => 'IELTS Preparation Hub',       'category' => 'Education',        'price' => 599,  'private' => true,  'img' => 'study'],
        ['name' => 'Board Exam Reviewers PH',     'category' => 'Education',        'price' => 499,  'private' => true,  'img' => 'exam'],
        ['name' => 'Speed Reading Academy',       'category' => 'Education',        'price' => 299,  'private' => false, 'img' => 'reading'],
        ['name' => 'Filipino Scholars Network',   'category' => 'Education',        'price' => 0,    'private' => false, 'img' => 'scholarship'],
        ['name' => 'Language Learning PH',        'category' => 'Education',        'price' => 299,  'private' => false, 'img' => 'language'],

        // Freelancing & Remote Work
        ['name' => 'Filipino Freelancers Hub',    'category' => 'Other',      'price' => 0,    'private' => false, 'img' => 'freelance'],
        ['name' => 'Virtual Assistants PH',       'category' => 'Other',      'price' => 399,  'private' => true,  'img' => 'remote'],
        ['name' => 'Upwork Success Academy',      'category' => 'Other',      'price' => 499,  'private' => true,  'img' => 'laptop'],
        ['name' => 'Graphic Design Philippines',  'category' => 'Design',           'price' => 399,  'private' => false, 'img' => 'design'],
        ['name' => 'Video Editing Pros PH',       'category' => 'Design',         'price' => 499,  'private' => true,  'img' => 'video-edit'],
        ['name' => 'Web Design Collective',       'category' => 'Design',           'price' => 599,  'private' => true,  'img' => 'webdesign'],

        // Personal Development
        ['name' => 'High-Performance Habits',     'category' => 'Other', 'price' => 799,  'private' => true,  'img' => 'habits'],
        ['name' => 'Public Speaking PH',          'category' => 'Other', 'price' => 499,  'private' => true,  'img' => 'speaking'],
        ['name' => 'Leadership Mastery Circle',   'category' => 'Other',       'price' => 999,  'private' => true,  'img' => 'leadership'],
        ['name' => 'Morning Routine Warriors',    'category' => 'Other', 'price' => 0,    'private' => false, 'img' => 'morning'],
        ['name' => 'Book Readers Philippines',    'category' => 'Other', 'price' => 0,    'private' => false, 'img' => 'books'],
        ['name' => 'Life Coaching Network PH',    'category' => 'Other',         'price' => 1299, 'private' => true,  'img' => 'coaching'],

        // Photography & Creative
        ['name' => 'Photography Philippines',     'category' => 'Design',      'price' => 399,  'private' => false, 'img' => 'photography'],
        ['name' => 'Travel Vloggers PH',          'category' => 'Other',           'price' => 299,  'private' => false, 'img' => 'travel'],
        ['name' => 'Food Photography Circle',     'category' => 'Design',      'price' => 399,  'private' => true,  'img' => 'food-photo'],
        ['name' => 'Pinoy Musicians Network',     'category' => 'Other',            'price' => 0,    'private' => false, 'img' => 'music'],
        ['name' => 'Indie Film Makers PH',        'category' => 'Design',             'price' => 499,  'private' => true,  'img' => 'film'],
        ['name' => 'Digital Art Community',       'category' => 'Design',              'price' => 299,  'private' => false, 'img' => 'art'],

        // Parenting & Lifestyle
        ['name' => 'Filipino Parents Network',    'category' => 'Other',        'price' => 0,    'private' => false, 'img' => 'family'],
        ['name' => 'OFW Support Community',       'category' => 'Other',        'price' => 0,    'private' => false, 'img' => 'community'],
        ['name' => 'Minimalist Living PH',        'category' => 'Other',        'price' => 0,    'private' => false, 'img' => 'minimal'],
        ['name' => 'Home Cooking Philippines',    'category' => 'Other',             'price' => 299,  'private' => false, 'img' => 'cooking'],
        ['name' => 'Pet Lovers Philippines',      'category' => 'Other',        'price' => 0,    'private' => false, 'img' => 'pets'],
        ['name' => 'Sustainable Living PH',       'category' => 'Other',        'price' => 0,    'private' => false, 'img' => 'nature'],

        // Gaming & Sports
        ['name' => 'Mobile Legends PH Elite',     'category' => 'Other',           'price' => 299,  'private' => true,  'img' => 'gaming'],
        ['name' => 'Esports Philippines',         'category' => 'Other',           'price' => 399,  'private' => true,  'img' => 'esports'],
        ['name' => 'Basketball Players PH',       'category' => 'Other',           'price' => 0,    'private' => false, 'img' => 'basketball'],
        ['name' => 'Fantasy Sports PH',           'category' => 'Other',           'price' => 299,  'private' => false, 'img' => 'sports'],

        // Real Estate & Architecture
        ['name' => 'Condo Investing PH',          'category' => 'Finance',      'price' => 999,  'private' => true,  'img' => 'condo'],
        ['name' => 'Airbnb Hosts Philippines',    'category' => 'Finance',      'price' => 599,  'private' => true,  'img' => 'airbnb'],
        ['name' => 'Interior Design PH',          'category' => 'Design',           'price' => 399,  'private' => false, 'img' => 'interior'],

        // Food & Restaurant
        ['name' => 'Food Business PH',            'category' => 'Business',    'price' => 599,  'private' => true,  'img' => 'restaurant2'],
        ['name' => 'Bakers & Pastry Chefs PH',   'category' => 'Other',             'price' => 299,  'private' => false, 'img' => 'baking'],
        ['name' => 'Coffee Lovers Philippines',   'category' => 'Other',             'price' => 0,    'private' => false, 'img' => 'coffee'],

        // HR & Careers
        ['name' => 'HR Professionals PH',         'category' => 'Other',           'price' => 499,  'private' => true,  'img' => 'hr'],
        ['name' => 'Career Growth Circle',        'category' => 'Other',           'price' => 399,  'private' => true,  'img' => 'career'],
        ['name' => 'Resume & Interview Mastery',  'category' => 'Other',           'price' => 299,  'private' => false, 'img' => 'interview'],
        ['name' => 'BPO Leaders Network',         'category' => 'Other',           'price' => 399,  'private' => true,  'img' => 'bpo'],

        // Agriculture & Environment
        ['name' => 'Urban Farming Philippines',   'category' => 'Other',      'price' => 0,    'private' => false, 'img' => 'farming'],
        ['name' => 'Aquaponics PH Community',     'category' => 'Other',      'price' => 299,  'private' => false, 'img' => 'aquaponics'],

        // Additional
        ['name' => 'NLP & Hypnotherapy PH',       'category' => 'Other',         'price' => 999,  'private' => true,  'img' => 'nlp'],
        ['name' => 'Skincare & Beauty PH',        'category' => 'Health',           'price' => 299,  'private' => false, 'img' => 'beauty'],
        ['name' => 'Nursing Board Reviewers',     'category' => 'Education',        'price' => 499,  'private' => true,  'img' => 'nursing'],
        ['name' => 'Architecture Board Prep',     'category' => 'Education',        'price' => 499,  'private' => true,  'img' => 'architecture'],
        ['name' => 'Law Students Philippines',    'category' => 'Education',        'price' => 599,  'private' => true,  'img' => 'law'],
        ['name' => 'Medical Professionals PH',    'category' => 'Health',       'price' => 699,  'private' => true,  'img' => 'medical'],
        ['name' => 'Teachers Connect PH',         'category' => 'Education',        'price' => 0,    'private' => false, 'img' => 'teachers'],
        ['name' => 'Social Workers Network PH',   'category' => 'Other',        'price' => 0,    'private' => false, 'img' => 'social-work'],
        ['name' => 'Engineers Guild PH',          'category' => 'Tech',      'price' => 399,  'private' => true,  'img' => 'engineering'],
        ['name' => 'Accountants Circle PH',       'category' => 'Finance',          'price' => 499,  'private' => true,  'img' => 'accounting'],
        ['name' => 'Podcast Creators PH',         'category' => 'Business',          'price' => 299,  'private' => false, 'img' => 'podcast'],
        ['name' => 'Newsletter Writers PH',       'category' => 'Business',          'price' => 299,  'private' => false, 'img' => 'newsletter'],
    ];

    // Picsum seeds that produce beautiful, varied photos
    private array $imgSeeds = [
        'startup' => '10', 'business' => '20', 'shipping' => '30', 'technology' => '40',
        'office' => '50', 'warehouse' => '60', 'restaurant' => '70', 'growth' => '80',
        'finance' => '90', 'crypto' => '100', 'realestate' => '110', 'money' => '120',
        'trading' => '130', 'income' => '140', 'investment' => '150', 'chart' => '160',
        'social' => '170', 'seo' => '180', 'content' => '190', 'video' => '200',
        'email' => '210', 'influencer' => '220', 'writing' => '230', 'branding' => '240',
        'coding' => '250', 'php' => '260', 'ai' => '270', 'mobile' => '280',
        'nocode' => '290', 'security' => '300', 'data' => '310', 'cloud' => '320',
        'fitness' => '330', 'workout' => '340', 'food' => '350', 'wellness' => '360',
        'yoga' => '370', 'gym' => '380', 'running' => '390', 'health' => '400',
        'teaching' => '410', 'study' => '420', 'exam' => '430', 'reading' => '440',
        'scholarship' => '450', 'language' => '460', 'freelance' => '470', 'remote' => '480',
        'laptop' => '490', 'design' => '500', 'video-edit' => '510', 'webdesign' => '520',
        'habits' => '530', 'speaking' => '540', 'leadership' => '550', 'morning' => '560',
        'books' => '570', 'coaching' => '580', 'photography' => '590', 'travel' => '600',
        'food-photo' => '610', 'music' => '620', 'film' => '630', 'art' => '640',
        'family' => '650', 'community' => '660', 'minimal' => '670', 'cooking' => '680',
        'pets' => '690', 'nature' => '700', 'gaming' => '710', 'esports' => '720',
        'basketball' => '730', 'sports' => '740', 'condo' => '750', 'airbnb' => '760',
        'interior' => '770', 'restaurant2' => '780', 'baking' => '790', 'coffee' => '800',
        'hr' => '810', 'career' => '820', 'interview' => '830', 'bpo' => '840',
        'farming' => '850', 'aquaponics' => '860', 'nlp' => '870', 'beauty' => '880',
        'nursing' => '890', 'architecture' => '900', 'law' => '910', 'medical' => '920',
        'teachers' => '930', 'social-work' => '940', 'engineering' => '950', 'accounting' => '960',
        'podcast' => '970', 'newsletter' => '980',
    ];

    public function run(): void
    {
        abort_unless(app()->isLocal(), 403, 'DevSeeder must only run in local environment.');

        // ── 1. Fixed test accounts ──────────────────────────────────────────────
        $owner = User::firstOrCreate(
            ['email' => 'owner@test.com'],
            [
                'name' => 'Test Owner',
                'username' => 'test-owner',
                'password' => Hash::make('password'),
            ]
        );

        User::firstOrCreate(
            ['email' => 'member@test.com'],
            [
                'name' => 'Test Member',
                'username' => 'test-member',
                'password' => Hash::make('password'),
            ]
        );

        // ── 2. 200 fixed random users (idempotent via deterministic emails) ────────
        $this->command->info('Upserting 200 dev users...');
        $users = collect();
        for ($i = 1; $i <= 200; $i++) {
            $users->push(User::firstOrCreate(
                ['email' => "devuser{$i}@test.com"],
                [
                    'name' => fake()->name(),
                    'username' => "devuser-{$i}",
                    'password' => Hash::make('password'),
                ]
            ));
        }

        // ── 3. Create 100 communities (idempotent via deterministic slug) ────────
        $this->command->info('Upserting 100 communities...');
        $bar = $this->command->getOutput()->createProgressBar(count($this->communities));
        $bar->start();

        foreach ($this->communities as $data) {
            $seed = $this->imgSeeds[$data['img']] ?? '42';
            $coverImage = "https://picsum.photos/seed/{$seed}/1200/400";
            $slug = Str::slug($data['name']);

            $community = Community::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $data['name'],
                    'owner_id' => $owner->id,
                    'description' => $this->description($data['category']),
                    'category' => $data['category'],
                    'cover_image' => $coverImage,
                    'is_private' => false, // all public for demo
                    'price' => $data['price'],
                    'currency' => 'PHP',
                ]
            );

            // Owner as admin member
            CommunityMember::firstOrCreate(
                ['community_id' => $community->id, 'user_id' => $owner->id],
                ['role' => CommunityMember::ROLE_ADMIN, 'joined_at' => now()->subMonths(rand(3, 12))]
            );

            // 20–120 random members with subscriptions (skip existing)
            $count = rand(20, min(120, $users->count()));
            $members = $users->random($count);

            foreach ($members as $user) {
                Subscription::firstOrCreate(
                    ['community_id' => $community->id, 'user_id' => $user->id],
                    [
                        'status' => Subscription::STATUS_ACTIVE,
                        'xendit_id' => 'dev_'.Str::uuid(),
                        'xendit_invoice_url' => 'https://checkout.xendit.co/dev',
                        'expires_at' => now()->addDays(rand(5, 60)),
                    ]
                );

                CommunityMember::firstOrCreate(
                    ['community_id' => $community->id, 'user_id' => $user->id],
                    ['role' => CommunityMember::ROLE_MEMBER, 'joined_at' => now()->subDays(rand(1, 180))]
                );
            }

            $bar->advance();
        }

        $bar->finish();
        $this->command->newLine(2);

        $this->command->info('Dev seed complete!');
        $this->command->table(
            ['Account', 'Email', 'Password'],
            [
                ['Owner',  'owner@test.com',  'password'],
                ['Member', 'member@test.com', 'password'],
            ]
        );
        $this->command->info('Communities: '.count($this->communities));
        $this->command->info('Users: 200 random + 2 fixed');
    }

    private function description(string $category): string
    {
        $descriptions = [
            'Entrepreneurship' => 'A thriving community for ambitious entrepreneurs ready to build, scale, and grow their businesses. Join thousands of founders sharing strategies, wins, and lessons learned.',
            'Business' => 'Connect with business owners and professionals who are serious about growing their ventures. Get access to exclusive resources, mentorship, and peer support.',
            'Finance' => 'Master your finances and build lasting wealth with guidance from experts and fellow community members who have done it before.',
            'Crypto' => 'Navigate the world of cryptocurrency with confidence. Get real-time insights, trading strategies, and market analysis from experienced traders.',
            'Real Estate' => 'Everything you need to start and grow your real estate investment portfolio in the Philippines. From due diligence to deal flow.',
            'Investing' => 'Learn how to make your money work harder. From stocks to funds, we cover all investment vehicles available to Filipino investors.',
            'Marketing' => 'Level up your marketing skills with proven strategies from top marketers. From paid ads to organic growth — everything works here.',
            'Content' => 'A creative space for content creators to sharpen their craft, grow their audience, and monetize their passion.',
            'Technology' => 'Stay ahead in the ever-evolving tech landscape. Share knowledge, collaborate on projects, and level up your technical skills.',
            'Web Dev' => 'A community for web developers to share knowledge, get code reviews, and stay updated with the latest tools and frameworks.',
            'Fitness' => 'Transform your body and mind with support from a community that keeps you accountable and motivated every single day.',
            'Nutrition' => 'Science-based nutrition guidance and community support to help you reach your health and body composition goals.',
            'Wellness' => 'A safe space to prioritize your mental, emotional, and physical well-being — because you deserve to feel your best.',
            'Education' => 'Accelerate your learning with structured courses, expert guidance, and a supportive community that celebrates every win.',
            'Freelancing' => 'Build a thriving freelance career with support from top earners. Find clients, raise your rates, and work on your terms.',
            'Design' => 'A community for designers to showcase work, get feedback, learn new tools, and connect with potential clients.',
            'Self Improvement' => 'Commit to becoming the best version of yourself. Daily habits, mindset shifts, and accountability that actually work.',
            'Leadership' => 'Develop the leadership skills that drive teams, companies, and communities forward in the modern world.',
            'Coaching' => 'Work with certified coaches and connect with others on their transformation journey. Real change starts here.',
            'Photography' => 'Sharpen your eye, master your gear, and build a photography business you love — with a community that inspires daily.',
            'Travel' => 'Explore the world smarter. Travel hacks, destination guides, and a community of adventurous souls like you.',
            'Music' => 'Whether you create, perform, or produce — this is your space to grow as a musician and connect with fellow artists.',
            'Parenting' => 'Navigate parenthood with confidence and joy. Share experiences, get advice, and build connections with parents who get it.',
            'Lifestyle' => 'Design a life you love — intentional, balanced, and aligned with your values. Community over competition.',
            'Gaming' => 'Level up your game, connect with elite players, and be part of a competitive community that pushes each other higher.',
            'Sports' => 'Connect with athletes and sports enthusiasts who share your passion for competition, fitness, and camaraderie.',
            'Food' => 'For those who live to eat — discover new recipes, cooking techniques, and connect with fellow food lovers.',
            'Food Business' => 'Turn your passion for food into a profitable business. Marketing, operations, and growth strategies for food entrepreneurs.',
            'Career' => 'Accelerate your career with insider knowledge, professional mentorship, and a network of driven professionals.',
            'Agriculture' => 'Sustainable farming practices, modern agricultural techniques, and a community of passionate growers.',
            'Beauty' => 'Skincare routines, beauty tips, and product recommendations from a community that believes in science-backed beauty.',
            'Healthcare' => 'A professional network for healthcare workers to share knowledge, support each other, and grow together.',
            'Engineering' => 'Connect with fellow engineers, stay updated on industry trends, and advance your professional career.',
            'E-Commerce' => 'Everything you need to build and scale a profitable e-commerce business in today\'s competitive marketplace.',
        ];

        return $descriptions[$category]
            ?? "A vibrant community for {$category} enthusiasts and professionals in the Philippines. Join us to learn, connect, and grow together.";
    }
}
