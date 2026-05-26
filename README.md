# Hướng Dẫn Sử Dụng Ứng Dụng Thanh Toán QR payOS (ReactJS + Laravel)

Dự án này là ứng dụng tích hợp thanh toán qua mã QR của cổng **payOS** chạy trên môi trường local, bao gồm:
*   **Frontend**: ReactJS (Thư mục `/frontend`, chạy ở cổng `8080`)
*   **Backend**: Laravel (Thư mục `/backend`, chạy ở cổng `3000`)
*   **Cơ sở dữ liệu**: SQLite (Zero-config, lưu tại `/backend/database/database.sqlite`)

---

## 🛠️ Yêu Cầu Hệ Thống Local

*   **Node.js**: Phiên bản `>= 16.14`
*   **PHP**: Phiên bản `>= 8.2` 
*   **Composer**: Thư viện quản lý gói PHP (Đã tải file `composer.phar` trong thư mục gốc dự án)

---

## ⚙️ Cấu Hình Dự Án

### 1. Cấu Hình Backend (`/backend/.env`)
Các thông số kết nối cơ sở dữ liệu SQLite và thông tin tài khoản payOS Sandbox/Production đã được thiết lập sẵn trong file `/backend/.env`:
```env
DB_CONNECTION=sqlite
DB_DATABASE=database.sqlite

# Thông tin tài khoản payOS của bạn
PAYOS_CLIENT_ID=your_client_id_here
PAYOS_API_KEY=your_api_key_here
PAYOS_CHECKSUM_KEY=your_checksum_key_here
```

### 2. Cấu Hinh Frontend (`/frontend/.env`)
Đã thiết lập cổng chạy Frontend ở `8080` và trỏ API URL về Backend ở cổng `3000`:
```env
REACT_APP_ORDER_URL=http://localhost:3000
REACT_APP_LISTS_BANK_URL=https://api.vietqr.io/v2/banks
REACT_APP_PAYOS_SCRIPT=https://cdn.payos.vn/payos-checkout/v1/stable/payos-initialize.js
PORT=8080
```

---

## 🚀 Hướng Dẫn Khởi Chạy Ứng Dụng

Vui lòng mở 2 cửa sổ dòng lệnh (Terminal/Command Prompt/PowerShell) riêng biệt để chạy song song Backend và Frontend:

### Bước 1: Khởi chạy Backend (Cổng 3000)
Mở cửa sổ dòng lệnh thứ nhất và chạy:
```bash
cd .\TestPAYOS\backend
php artisan serve --port=3000
```
> Khi màn hình hiện dòng `INFO Server running on [http://127.0.0.1:3000]` là Backend đã sẵn sàng.

### Bước 2: Khởi chạy Frontend (Cổng 8080)
Mở cửa sổ dòng lệnh thứ hai và chạy:
```powershell
cd .\TestPAYOS\frontend
npm start
```
> Trình duyệt sẽ tự động mở trang web tại địa chỉ `http://localhost:8080`.

---

## 💡 Cơ Chế Đồng Bộ Trạng Thái Thanh Toán Đặc Biệt Ở Local

Vì ứng dụng chạy ở môi trường nội bộ (`localhost`), máy chủ của payOS **không thể gửi trực tiếp thông báo thanh toán (Webhook)** về máy của bạn được. 

Để giải quyết vấn đề này, ứng dụng đã được tối ưu hóa như sau:
1. **Kiểm tra trạng thái thời gian thực (Real-time Pulling)**: Khi bạn vừa thanh toán thành công và được chuyển hướng về trang Kết quả (`http://localhost:8080/result`), Frontend sẽ lập tức gọi API của Backend.
2. **Đồng bộ tự động**: Laravel Backend sẽ gọi trực tiếp sang API của payOS để lấy trạng thái mới nhất của đơn hàng đó.
3. **Cập nhật dữ liệu**: 
   - Nếu đơn hàng đã được thanh toán thành công (`PAID`), Backend sẽ tự động cập nhật Database SQLite nội bộ.
   - Đồng thời, tự động chuyển đổi thông tin giao dịch thực tế trên payOS thành dữ liệu giả lập Webhook (`webhook_snapshot`) để website hiển thị đầy đủ chi tiết lịch sử giao dịch (mã ngân hàng, mã tham chiếu giao dịch, tên người chuyển khoản...).
4. **Kết quả hiển thị**: Màn hình website sẽ tự động hiển thị **"Đã thanh toán"** mà bạn không cần phải thực hiện bất kỳ thao tác cấu hình phức tạp nào khác.

---

## ⚠️ Lưu Ý Về Số Tiền Thanh Toán Tối Thiểu
*   Theo quy định chuyển khoản VietQR của đa số ứng dụng Ngân hàng Việt Nam hiện nay, số tiền tối thiểu cho mỗi giao dịch chuyển khoản liên ngân hàng là **2,000 VNĐ**.
*   Form tạo đơn hàng mặc định đã được cấu hình giá trị là **5,000 VNĐ** (chấp nhận thanh toán hợp lệ trên mọi ngân hàng).
*   Website đã tích hợp sẵn cảnh báo nếu bạn cố tình nhập số tiền nhỏ hơn 2,000 VNĐ.
