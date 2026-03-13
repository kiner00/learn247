import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
  stages: [
    { duration: '10s', target: 20 },   // ramp up
    { duration: '20s', target: 100 },  // ramp up to 100 users
    { duration: '10s', target: 0 },    // ramp down
  ],
  thresholds: {
    http_req_duration: ['p(95)<2000'], // 95% of requests must finish < 2s
    http_req_failed: ['rate<0.05'],    // error rate must be < 5%
  },
};

const BASE_URL = 'https://curzzo.roguenet.dev';

export default function () {
  // Public community listing
  const res1 = http.get(`${BASE_URL}/api/communities`);
  check(res1, { 'communities status 200': (r) => r.status === 200 });

  sleep(1);
}
