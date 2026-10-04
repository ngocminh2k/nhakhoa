// scripts/test_publish.mjs
async function run() {
  const url = "http://127.0.0.1:8080/api/v1/posts";
  const apiKey = "kd_db6742ceea37b12d20c902eed5d9398e2a76628a51b8fcbb";

  const payload = {
    title: "Hướng Dẫn Chăm Sóc Răng Miệng Sau Khi Cấy Ghép Implant",
    slug: "huong-dan-cham-soc-rang-mieng-sau-khi-cay-ghep-implant",
    excerpt: "Những lưu ý quan trọng về chế độ ăn uống và vệ sinh răng miệng sau phẫu thuật cấy ghép trụ Implant chuẩn y khoa.",
    content: `
      <h2>1. Chế độ ăn uống trong 48 giờ đầu</h2>
      <p>Sau khi cấy ghép Implant tại Nha Khoa Kim Dung, quý khách nên dùng thức ăn mềm, nguội như cháo dinh dưỡng, sữa tươi, súp ấm. Tránh nhai trực tiếp vào vùng vừa đặt trụ.</p>
      <h2>2. Vệ sinh và súc miệng an toàn</h2>
      <p>Không dùng bàn chải chà xát mạnh vào vị trí vết thương. Sử dụng dung dịch súc miệng sát khuẩn chứa Chlorhexidine 0.12% theo chỉ định của bác sĩ.</p>
      <h2>3. Lịch tái khám định kỳ</h2>
      <p>Quý khách tái khám cắt chỉ sau 7 - 10 ngày để bác sĩ kiểm tra mức độ tích hợp xương và sự lành thương của mô lợi.</p>
    `,
    featured_image: "/website/assets/images/hero-clinic.jpg",
    status: "published"
  };

  const res = await fetch(url, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "Authorization": `Bearer ${apiKey}`
    },
    body: JSON.stringify(payload)
  });

  const body = await res.json();
  console.log("STATUS:", res.status);
  console.log("RESPONSE:", JSON.stringify(body, null, 2));
}

run();
