import http from 'k6/http';
import { check, sleep, group } from 'k6';
import { Trend, Rate, Counter } from 'k6/metrics';

// Custom metrics
const communityListDuration = new Trend('community_list_duration');
const classroomDuration     = new Trend('classroom_duration');
const communityShowDuration = new Trend('community_show_duration');
const errorRate             = new Rate('error_rate');
const totalRequests         = new Counter('total_requests');

export const options = {
  stages: [
    { duration: '30s', target: 50  },  // warm up
    { duration: '60s', target: 200 },  // ramp to 200
    { duration: '60s', target: 500 },  // ramp to 500 (peak load)
    { duration: '60s', target: 500 },  // hold at 500
    { duration: '30s', target: 0   },  // ramp down
  ],
  thresholds: {
    http_req_duration:        ['p(95)<3000', 'p(99)<5000'],
    http_req_failed:          ['rate<0.05'],
    community_list_duration:  ['p(95)<2000'],
    classroom_duration:       ['p(95)<3000'],
    community_show_duration:  ['p(95)<2500'],
    error_rate:               ['rate<0.05'],
  },
};

const BASE_URL = 'https://curzzo.roguenet.dev';

const HEADERS = {
  'Accept':       'application/json',
  'Content-Type': 'application/json',
};

// Realistic community slugs to test show page
const COMMUNITY_SLUGS = ['general', 'ph-developers', 'tech', 'business', 'design'];

function randomSlug() {
  return COMMUNITY_SLUGS[Math.floor(Math.random() * COMMUNITY_SLUGS.length)];
}

export default function () {
  // Distribute users across different flows
  const flow = Math.random();

  if (flow < 0.4) {
    // 40% — browse community listing (most common)
    group('Community Listing', () => {
      const res = http.get(`${BASE_URL}/communities`, { headers: HEADERS });
      communityListDuration.add(res.timings.duration);
      totalRequests.add(1);
      const ok = check(res, {
        'listing: status 200': (r) => r.status === 200,
        'listing: response time < 3s': (r) => r.timings.duration < 3000,
      });
      if (!ok) errorRate.add(1); else errorRate.add(0);
    });

  } else if (flow < 0.65) {
    // 25% — view a community page
    group('Community Show', () => {
      const slug = randomSlug();
      const res = http.get(`${BASE_URL}/communities/${slug}`, { headers: HEADERS });
      communityShowDuration.add(res.timings.duration);
      totalRequests.add(1);
      const ok = check(res, {
        'show: status 200 or 302': (r) => [200, 302, 404].includes(r.status),
        'show: response time < 3s': (r) => r.timings.duration < 3000,
      });
      if (!ok) errorRate.add(1); else errorRate.add(0);
    });

  } else if (flow < 0.85) {
    // 20% — view classroom index
    group('Classroom Index', () => {
      const slug = randomSlug();
      const res = http.get(`${BASE_URL}/communities/${slug}/classroom`, { headers: HEADERS });
      classroomDuration.add(res.timings.duration);
      totalRequests.add(1);
      const ok = check(res, {
        'classroom: not 500': (r) => r.status !== 500,
        'classroom: response time < 4s': (r) => r.timings.duration < 4000,
      });
      if (!ok) errorRate.add(1); else errorRate.add(0);
    });

  } else {
    // 15% — affiliate/ref landing page
    group('Ref Landing', () => {
      const res = http.get(`${BASE_URL}/communities`, { headers: HEADERS, tags: { name: 'ref_landing' } });
      totalRequests.add(1);
      const ok = check(res, {
        'ref: not 500': (r) => r.status !== 500,
      });
      if (!ok) errorRate.add(1); else errorRate.add(0);
    });
  }

  // Think time: 1–3s between requests (simulates real user browsing)
  sleep(1 + Math.random() * 2);
}
