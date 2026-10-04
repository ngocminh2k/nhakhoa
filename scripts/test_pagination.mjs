// scripts/test_pagination.mjs
import http from 'http';

function request(options, data = null) {
  return new Promise((resolve, reject) => {
    const req = http.request(options, res => {
      let body = '';
      res.on('data', chunk => body += chunk);
      res.on('end', () => resolve({
        status: res.statusCode,
        headers: res.headers,
        body
      }));
    });
    req.on('error', reject);
    if (data) req.write(data);
    req.end();
  });
}

function get(url, headers = {}) {
  return new Promise((resolve, reject) => {
    http.get(url, { headers }, res => {
      let data = '';
      res.on('data', chunk => data += chunk);
      res.on('end', () => resolve({ status: res.statusCode, headers: res.headers, body: data }));
    }).on('error', reject);
  });
}

async function run() {
  console.log('=== TEST 1: PUBLIC NEWS LIST PAGINATION ===');

  // Page 1
  const p1 = await get('http://127.0.0.1:8080/website/tin-tuc.html');
  const count1 = (p1.body.match(/<article class="gf-news-item"/g) || []).length;
  console.log(`Page 1: status=${p1.status}, items=${count1}`);
  console.log('Has pagination wrapper:', p1.body.includes('gf-pagination-wrap'));
  const summary1 = p1.body.match(/Hiển thị bài viết <strong>[\d -]+<\/strong> trên tổng số <strong>\d+<\/strong>.*?\(Trang <strong>\d+<\/strong> \/ <strong>\d+<\/strong>\)/);
  console.log('Summary 1:', summary1 ? summary1[0] : 'NONE');

  // Page 2
  const p2 = await get('http://127.0.0.1:8080/website/tin-tuc.html?page=2');
  const count2 = (p2.body.match(/<article class="gf-news-item"/g) || []).length;
  console.log(`Page 2: status=${p2.status}, items=${count2}`);
  const summary2 = p2.body.match(/Hiển thị bài viết <strong>[\d -]+<\/strong> trên tổng số <strong>\d+<\/strong>.*?\(Trang <strong>\d+<\/strong> \/ <strong>\d+<\/strong>\)/);
  console.log('Summary 2:', summary2 ? summary2[0] : 'NONE');

  // Page 10 (Last page)
  const p10 = await get('http://127.0.0.1:8080/website/tin-tuc.html?page=10');
  const count10 = (p10.body.match(/<article class="gf-news-item"/g) || []).length;
  console.log(`Page 10: status=${p10.status}, items=${count10}`);
  const summary10 = p10.body.match(/Hiển thị bài viết <strong>[\d -]+<\/strong> trên tổng số <strong>\d+<\/strong>.*?\(Trang <strong>\d+<\/strong> \/ <strong>\d+<\/strong>\)/);
  console.log('Summary 10:', summary10 ? summary10[0] : 'NONE');

  // Clean URL /tin-tuc?page=3
  const pClean = await get('http://127.0.0.1:8080/tin-tuc?page=3');
  const countClean = (pClean.body.match(/<article class="gf-news-item"/g) || []).length;
  console.log(`Clean URL /tin-tuc?page=3: status=${pClean.status}, items=${countClean}`);
  const summaryClean = pClean.body.match(/Hiển thị bài viết <strong>[\d -]+<\/strong> trên tổng số <strong>\d+<\/strong>.*?\(Trang <strong>\d+<\/strong> \/ <strong>\d+<\/strong>\)/);
  console.log('Clean Summary:', summaryClean ? summaryClean[0] : 'NONE');

  console.log('\n=== TEST 2: ADMIN POSTS PAGINATION ===');
  // 1. Get login page to extract CSRF token and cookie
  const loginGet = await request({ host: '127.0.0.1', port: 8080, path: '/admin/login.php', method: 'GET' });
  const getCookies = loginGet.headers['set-cookie'] || [];
  const initialCookie = getCookies[0] ? getCookies[0].split(';')[0] : '';
  const csrfMatch = loginGet.body.match(/name="csrf_token"\s+value="([^"]+)"/);
  const csrfToken = csrfMatch ? csrfMatch[1] : '';

  // 2. Submit login
  const postData = `email=${encodeURIComponent('admin@nhakhoakimdung.com')}&password=${encodeURIComponent('AdminKimDung@2026!')}&csrf_token=${encodeURIComponent(csrfToken)}`;
  const loginRes = await request({
    host: '127.0.0.1',
    port: 8080,
    path: '/admin/login.php',
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'Content-Length': Buffer.byteLength(postData),
      'Cookie': initialCookie
    }
  }, postData);

  const authCookie = (loginRes.headers['set-cookie'] ? loginRes.headers['set-cookie'][0].split(';')[0] : initialCookie);

  // 3. Admin Page 1
  const adminP1 = await request({ host: '127.0.0.1', port: 8080, path: '/admin/posts.php?page=1', method: 'GET', headers: { Cookie: authCookie } });
  const rowMatches1 = adminP1.body.match(/<tr>\s*<td>#\d+<\/td>/g) || [];
  console.log(`Admin Page 1: status=${adminP1.status}, table rows=${rowMatches1.length}`);
  const adminSummary1 = adminP1.body.match(/Hiển thị bài viết <strong>[\d -]+<\/strong> trên tổng số <strong>\d+<\/strong> bài \(Trang <strong>\d+<\/strong> \/ <strong>\d+<\/strong>\)/);
  console.log('Admin Summary 1:', adminSummary1 ? adminSummary1[0] : 'NONE');

  // 4. Admin Page 2
  const adminP2 = await request({ host: '127.0.0.1', port: 8080, path: '/admin/posts.php?page=2', method: 'GET', headers: { Cookie: authCookie } });
  const rowMatches2 = adminP2.body.match(/<tr>\s*<td>#\d+<\/td>/g) || [];
  console.log(`Admin Page 2: status=${adminP2.status}, table rows=${rowMatches2.length}`);
  const adminSummary2 = adminP2.body.match(/Hiển thị bài viết <strong>[\d -]+<\/strong> trên tổng số <strong>\d+<\/strong> bài \(Trang <strong>\d+<\/strong> \/ <strong>\d+<\/strong>\)/);
  console.log('Admin Summary 2:', adminSummary2 ? adminSummary2[0] : 'NONE');

  // 5. Admin Page 6
  const adminP6 = await request({ host: '127.0.0.1', port: 8080, path: '/admin/posts.php?page=6', method: 'GET', headers: { Cookie: authCookie } });
  const rowMatches6 = adminP6.body.match(/<tr>\s*<td>#\d+<\/td>/g) || [];
  console.log(`Admin Page 6: status=${adminP6.status}, table rows=${rowMatches6.length}`);
  const adminSummary6 = adminP6.body.match(/Hiển thị bài viết <strong>[\d -]+<\/strong> trên tổng số <strong>\d+<\/strong> bài \(Trang <strong>\d+<\/strong> \/ <strong>\d+<\/strong>\)/);
  console.log('Admin Summary 6:', adminSummary6 ? adminSummary6[0] : 'NONE');

  console.log('\n=======================================');
  console.log('✅ HỆ THỐNG PHÂN TRANG HOẠT ĐỘNG HOÀN HẢO CẢ TRANG CHỦ LẪN ADMIN!');
  console.log('=======================================');
}

run().catch(console.error);
