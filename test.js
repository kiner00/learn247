/**
 * Curzzo Full Load Test — k6
 *
 * Scenarios:
 *   1. anonymous   — unauthenticated browsing (highest volume)
 *   2. members     — logged-in member actions
 *   3. creators    — owner dashboard / settings / analytics
 *
 * Seeded test accounts (from DevSeeder):
 *   devuser1@test.com … devuser200@test.com  password: "password"
 *   owner@test.com                            password: "password"
 *
 * IMPORTANT before running:
 *   The POST /login route has throttle:5,1 (5 req/min per IP).
 *   Temporarily raise it in routes/web.php → throttle:300,1
 *   OR run: docker exec curzzo_app php artisan cache:clear
 *   to reset rate-limit counters between runs.
 *
 * Run:
 *   k6 run test.js
 *   k6 run --out json=results.json test.js
 */

import http from 'k6/http';
import { check, sleep, group } from 'k6';
import { Trend, Rate, Counter } from 'k6/metrics';

const BASE_URL = 'https://curzzo.roguenet.dev';
const NUM_USERS = 200;

// ── Custom metrics ────────────────────────────────────────────────────────────
const loginDuration     = new Trend('login_duration',     true);
const feedDuration      = new Trend('feed_duration',      true);
const classroomDuration = new Trend('classroom_duration', true);
const listingDuration   = new Trend('listing_duration',   true);
const dashboardDuration = new Trend('dashboard_duration', true);
const errorRate         = new Rate('error_rate');
const totalRequests     = new Counter('total_requests');

// ── Community slugs from DevSeeder ───────────────────────────────────────────
const ALL_SLUGS = [
    'startup-founders-ph', 'e-commerce-mastery', 'dropshipping-academy-ph',
    'digital-entrepreneurs-hub', 'sme-growth-circle', 'amazon-fba-philippines',
    'franchise-network-ph', 'business-scaling-secrets',
    'stock-market-pinoys', 'crypto-traders-ph', 'real-estate-investors-club',
    'personal-finance-pilipinas', 'forex-trading-academy', 'passive-income-builders',
    'bdo-wealth-hackers', 'uitf-etf-philippines',
    'facebook-ads-mastery-ph', 'seo-philippines-community', 'content-creator-academy',
    'tiktok-marketing-ph', 'email-marketing-pros', 'influencer-growth-lab',
    'copywriting-collective-ph', 'brand-building-bootcamp',
    'pinoy-developers-network', 'laravel-philippines', 'ai-machine-learning-ph',
    'mobile-dev-circle', 'no-code-builders-ph', 'cybersecurity-philippines',
    'data-science-ph', 'cloud-computing-circle',
    'pinoy-fitness-nation', 'home-workout-heroes', 'keto-diet-philippines',
    'mental-health-warriors-ph', 'yoga-mindfulness-ph', 'bodybuilding-philippines',
    'running-community-ph', 'intermittent-fasting-ph',
    'online-teaching-philippines', 'ielts-preparation-hub', 'board-exam-reviewers-ph',
    'speed-reading-academy', 'filipino-scholars-network', 'language-learning-ph',
    'filipino-freelancers-hub', 'virtual-assistants-ph', 'upwork-success-academy',
    'graphic-design-philippines', 'video-editing-pros-ph', 'web-design-collective',
    'high-performance-habits', 'public-speaking-ph', 'leadership-mastery-circle',
    'morning-routine-warriors', 'book-readers-philippines', 'life-coaching-network-ph',
    'photography-philippines', 'travel-vloggers-ph', 'pinoy-musicians-network',
    'digital-art-community', 'filipino-parents-network', 'ofw-support-community',
    'minimalist-living-ph', 'home-cooking-philippines', 'pet-lovers-philippines',
    'sustainable-living-ph', 'mobile-legends-ph-elite', 'esports-philippines',
    'basketball-players-ph', 'condo-investing-ph', 'airbnb-hosts-philippines',
    'interior-design-ph', 'food-business-ph', 'hr-professionals-ph',
    'career-growth-circle', 'resume-interview-mastery', 'bpo-leaders-network',
    'urban-farming-philippines', 'skincare-beauty-ph', 'engineers-guild-ph',
    'accountants-circle-ph', 'podcast-creators-ph', 'newsletter-writers-ph',
];

// Free communities (no membership fee — always accessible to members)
const FREE_SLUGS = [
    'digital-entrepreneurs-hub', 'personal-finance-pilipinas', 'bdo-wealth-hackers',
    'uitf-etf-philippines', 'tiktok-marketing-ph', 'pinoy-developers-network',
    'no-code-builders-ph', 'pinoy-fitness-nation', 'home-workout-heroes',
    'mental-health-warriors-ph', 'yoga-mindfulness-ph', 'running-community-ph',
    'intermittent-fasting-ph', 'speed-reading-academy', 'filipino-scholars-network',
    'language-learning-ph', 'filipino-freelancers-hub', 'morning-routine-warriors',
    'book-readers-philippines', 'pinoy-musicians-network', 'digital-art-community',
    'filipino-parents-network', 'ofw-support-community', 'minimalist-living-ph',
    'home-cooking-philippines', 'pet-lovers-philippines', 'sustainable-living-ph',
    'basketball-players-ph', 'urban-farming-philippines', 'podcast-creators-ph',
    'newsletter-writers-ph',
];

// ── Load test options ─────────────────────────────────────────────────────────
export const options = {
    scenarios: {
        // 1. Guest users — highest volume (public pages only)
        anonymous: {
            executor: 'ramping-vus',
            stages: [
                { duration: '30s', target: 50  },
                { duration: '60s', target: 200 },
                { duration: '60s', target: 300 },
                { duration: '60s', target: 300 },
                { duration: '30s', target: 0   },
            ],
            exec: 'anonymousBrowsing',
        },
        // 2. Logged-in members
        members: {
            executor: 'ramping-vus',
            startTime: '15s',
            stages: [
                { duration: '30s', target: 20  },
                { duration: '60s', target: 80  },
                { duration: '60s', target: 80  },
                { duration: '30s', target: 0   },
            ],
            exec: 'memberBrowsing',
        },
        // 3. Community owner / creator
        creators: {
            executor: 'constant-vus',
            vus: 5,
            duration: '3m30s',
            startTime: '20s',
            exec: 'creatorDashboard',
        },
    },
    thresholds: {
        http_req_duration:      ['p(95)<4000', 'p(99)<6000'],
        http_req_failed:        ['rate<0.05'],
        listing_duration:       ['p(95)<2000'],
        feed_duration:          ['p(95)<3000'],
        classroom_duration:     ['p(95)<3500'],
        dashboard_duration:     ['p(95)<4000'],
        error_rate:             ['rate<0.05'],
    },
};

// ── Auth helpers ──────────────────────────────────────────────────────────────
/**
 * Extracts XSRF-TOKEN from the cookie jar (set by GET /login).
 * Laravel sets this automatically; Axios sends it back as X-XSRF-TOKEN.
 */
function getXsrfToken() {
    const cookies = http.cookieJar().cookiesForURL(BASE_URL);
    if (cookies['XSRF-TOKEN'] && cookies['XSRF-TOKEN'].length > 0) {
        return decodeURIComponent(cookies['XSRF-TOKEN'][0]);
    }
    // Fallback: extract from HTML meta tag
    const res = http.get(`${BASE_URL}/login`);
    const match = res.body.match(/<meta[^>]+name="csrf-token"[^>]+content="([^"]+)"/);
    return match ? match[1] : '';
}

function doLogin(email, password) {
    // 1. Hit login page to seed the session + XSRF-TOKEN cookie
    http.get(`${BASE_URL}/login`);

    const xsrf = getXsrfToken();

    // 2. POST login — Inertia-style JSON with X-XSRF-TOKEN
    const start = Date.now();
    const res = http.post(
        `${BASE_URL}/login`,
        JSON.stringify({ email, password }),
        {
            headers: {
                'Content-Type':    'application/json',
                'Accept':          'application/json, text/plain, */*',
                'X-Requested-With': 'XMLHttpRequest',
                'X-Inertia':       'true',
                'X-XSRF-TOKEN':    xsrf,
            },
        }
    );
    loginDuration.add(Date.now() - start);

    return check(res, {
        'login: success (200/302)': (r) => r.status === 200 || r.status === 302,
        'login: not throttled':     (r) => r.status !== 429,
    });
}

// Per-VU login state (k6 module-level vars are per-VU)
let isLoggedIn = false;

function ensureMemberLogin(vuId) {
    if (isLoggedIn) return true;
    const userNum = ((vuId - 1) % NUM_USERS) + 1;
    const ok = doLogin(`devuser${userNum}@test.com`, 'password');
    if (ok) isLoggedIn = true;
    sleep(0.5 + Math.random()); // stagger logins to reduce thundering herd
    return ok;
}

function ensureOwnerLogin() {
    if (isLoggedIn) return true;
    const ok = doLogin('owner@test.com', 'password');
    if (ok) isLoggedIn = true;
    sleep(0.5);
    return ok;
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function pick(arr) {
    return arr[Math.floor(Math.random() * arr.length)];
}

function track(ok) {
    errorRate.add(ok ? 0 : 1);
    totalRequests.add(1);
}

// ── Scenario 1: Anonymous browsing ───────────────────────────────────────────
export function anonymousBrowsing() {
    const flow = Math.random();

    if (flow < 0.30) {
        group('Community Listing', () => {
            const res = http.get(`${BASE_URL}/communities`);
            listingDuration.add(res.timings.duration);
            track(check(res, {
                'listing 200':  (r) => r.status === 200,
                'listing <2.5s': (r) => r.timings.duration < 2500,
            }));
        });

    } else if (flow < 0.55) {
        group('Community About Page', () => {
            const slug = pick(ALL_SLUGS);
            const res = http.get(`${BASE_URL}/communities/${slug}/about`);
            track(check(res, {
                'about not 500': (r) => r.status !== 500,
                'about <3s':     (r) => r.timings.duration < 3000,
            }));
        });

    } else if (flow < 0.75) {
        group('Classroom (Public)', () => {
            const slug = pick(ALL_SLUGS);
            const res = http.get(`${BASE_URL}/communities/${slug}/classroom`);
            classroomDuration.add(res.timings.duration);
            track(check(res, {
                'classroom not 500': (r) => r.status !== 500,
                'classroom <4s':     (r) => r.timings.duration < 4000,
            }));
        });

    } else if (flow < 0.88) {
        group('Ref Landing Page', () => {
            // Simulate affiliate link click (slug as code — won't match but tests the route)
            const code = pick(['test', 'demo', 'promo', 'special']);
            const res = http.get(`${BASE_URL}/ref/${code}`, { redirects: 3 });
            track(check(res, {
                'ref not 500': (r) => r.status !== 500,
            }));
        });

    } else {
        group('Public Profile', () => {
            const n = Math.floor(Math.random() * NUM_USERS) + 1;
            const res = http.get(`${BASE_URL}/profile/devuser-${n}`);
            track(check(res, {
                'profile not 500': (r) => r.status !== 500,
            }));
        });
    }

    sleep(1 + Math.random() * 2);
}

// ── Scenario 2: Member browsing ───────────────────────────────────────────────
export function memberBrowsing() {
    if (!ensureMemberLogin(__VU)) {
        sleep(5); // login was throttled — wait and retry next iteration
        return;
    }

    const flow = Math.random();

    if (flow < 0.25) {
        group('Community Feed (Show)', () => {
            const slug = pick(ALL_SLUGS);
            const res = http.get(`${BASE_URL}/communities/${slug}`);
            feedDuration.add(res.timings.duration);
            track(check(res, {
                'feed not 500': (r) => r.status !== 500,
                'feed <3s':     (r) => r.timings.duration < 3000,
            }));
        });

    } else if (flow < 0.45) {
        group('Classroom (Member)', () => {
            const slug = pick(ALL_SLUGS);
            const res = http.get(`${BASE_URL}/communities/${slug}/classroom`);
            classroomDuration.add(res.timings.duration);
            track(check(res, {
                'classroom member not 500': (r) => r.status !== 500,
            }));
        });

    } else if (flow < 0.58) {
        group('Community Chat', () => {
            const slug = pick(FREE_SLUGS); // free communities = no gate
            const res = http.get(`${BASE_URL}/communities/${slug}/chat`);
            track(check(res, {
                'chat not 500': (r) => r.status !== 500,
            }));
        });

    } else if (flow < 0.68) {
        group('Leaderboard', () => {
            const slug = pick(ALL_SLUGS);
            const res = http.get(`${BASE_URL}/communities/${slug}/leaderboard`);
            track(check(res, {
                'leaderboard not 500': (r) => r.status !== 500,
            }));
        });

    } else if (flow < 0.76) {
        group('Notifications', () => {
            const res = http.get(`${BASE_URL}/notifications/recent`);
            track(check(res, {
                'notifications 200': (r) => r.status === 200,
            }));
        });

    } else if (flow < 0.84) {
        group('My Affiliates', () => {
            const res = http.get(`${BASE_URL}/my-affiliates`);
            track(check(res, {
                'affiliates not 500': (r) => r.status !== 500,
            }));
        });

    } else if (flow < 0.91) {
        group('Account Settings', () => {
            const res = http.get(`${BASE_URL}/settings`);
            track(check(res, {
                'settings not 500': (r) => r.status !== 500,
            }));
        });

    } else {
        group('Badges', () => {
            const res = http.get(`${BASE_URL}/badges`);
            track(check(res, {
                'badges not 500': (r) => r.status !== 500,
            }));
        });
    }

    sleep(1 + Math.random() * 3);
}

// ── Scenario 3: Creator / owner ───────────────────────────────────────────────
export function creatorDashboard() {
    if (!ensureOwnerLogin()) {
        sleep(5);
        return;
    }

    const flow = Math.random();

    if (flow < 0.35) {
        group('Creator Dashboard', () => {
            const res = http.get(`${BASE_URL}/creator/dashboard`);
            dashboardDuration.add(res.timings.duration);
            track(check(res, {
                'creator dashboard 200': (r) => r.status === 200,
                'creator dashboard <5s': (r) => r.timings.duration < 5000,
            }));
        });

    } else if (flow < 0.55) {
        group('Community Settings', () => {
            const slug = pick(ALL_SLUGS);
            const res = http.get(`${BASE_URL}/communities/${slug}/settings`);
            track(check(res, {
                'settings not 500': (r) => r.status !== 500,
            }));
        });

    } else if (flow < 0.75) {
        group('Community Analytics', () => {
            const slug = pick(ALL_SLUGS);
            const res = http.get(`${BASE_URL}/communities/${slug}/analytics`);
            track(check(res, {
                'analytics not 500': (r) => r.status !== 500,
            }));
        });

    } else if (flow < 0.88) {
        group('Community Members', () => {
            const slug = pick(ALL_SLUGS);
            const res = http.get(`${BASE_URL}/communities/${slug}/members`);
            track(check(res, {
                'members not 500': (r) => r.status !== 500,
            }));
        });

    } else {
        group('Creator Plan Page', () => {
            const res = http.get(`${BASE_URL}/creator/plan`);
            track(check(res, {
                'plan page not 500': (r) => r.status !== 500,
            }));
        });
    }

    sleep(2 + Math.random() * 4);
}
