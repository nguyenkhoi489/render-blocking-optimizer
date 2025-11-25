# ⚡ Render Blocking Optimizer

Plugin WordPress tối ưu hóa CSS và JavaScript để giảm render blocking, cải thiện tốc độ tải trang và điểm số PageSpeed Insights.

## 📋 Mô tả

Render Blocking Optimizer là một plugin mạnh mẽ giúp tối ưu hóa cách trình duyệt tải và xử lý tài nguyên CSS/JS của website. Plugin tự động thêm các thuộc tính `defer`, `async` và tối ưu hóa việc tải fonts, giúp cải thiện đáng kể hiệu suất trang web.

## ✨ Tính năng chính

### 🚀 Tối ưu JavaScript
- **Defer JavaScript**: Tự động thêm thuộc tính `defer` cho tất cả script
- **Loại trừ jQuery linh hoạt**: 
  - Loại trừ toàn site
  - Loại trừ theo URL cụ thể (hỗ trợ checkout, cart, widget pages)
- **Danh sách loại trừ tùy chỉnh**: Thêm script handles/URLs cần giữ nguyên

### 🎨 Tối ưu CSS
- **Async CSS Loading**: Load CSS không đồng bộ với fallback
- **Bảo vệ Critical CSS**: Tự động giữ nguyên CSS quan trọng
- **Danh sách CSS được bảo vệ**:
  - Flatsome (style, base-css, shop)
  - WooCommerce (layout, general, smallscreen)
  - WordPress core (wp-block-library, global-styles)

### 🔤 Tối ưu Fonts
- **Preload Local Fonts**: 
  - Tự động phát hiện fonts trong `/wp-content/fonts/`
  - Preload Flatsome icon fonts
  - Hỗ trợ cấu trúc thư mục con
- **Google Fonts Optimization**:
  - Tự động thêm `display=swap`
  - Preconnect & DNS-prefetch
  - Tự động disable nếu có local fonts tương ứng
  - Preload fonts quan trọng từ theme settings

### ⚡ Resource Hints
- Preconnect cho Google Fonts
- DNS-prefetch cho các domain bên ngoài
- Preload cho critical CSS và fonts
- Preload fonts từ theme customizer

## 📦 Cài đặt

### Cài đặt thủ công
1. Tải plugin về và giải nén
2. Upload thư mục `render-blocking-optimizer` vào `/wp-content/plugins/`
3. Kích hoạt plugin trong WordPress admin
4. Vào **Settings > RB Optimizer** để cấu hình

### Cài đặt qua Git
```bash
cd wp-content/plugins/
git clone [repository-url] render-blocking-optimizer
```

## ⚙️ Cấu hình

### Tùy chọn cơ bản

#### Defer JavaScript
- ✅ Bật để thêm `defer` cho tất cả JavaScript
- Giúp JS không chặn quá trình render trang

#### Async CSS
- ✅ Bật để load CSS không đồng bộ
- CSS quan trọng vẫn được bảo vệ tự động

#### Loại trừ jQuery (Toàn site)
- ✅ Bật nếu website dùng nhiều plugin jQuery
- jQuery sẽ load bình thường trên toàn bộ site

#### Loại trừ jQuery theo URL
Nhập mỗi URL một dòng, hỗ trợ:
- URL đầy đủ: `https://yourdomain.com/widget/`
- Partial URL: `/checkout`, `/cart`, `/my-account`

**Ví dụ:**
```
https://www.yoursite.com/widget/
/checkout
/cart
/product-category
```

#### Loại trừ Scripts
Danh sách handle hoặc URL cách nhau bằng dấu phẩy:
```
google-analytics, gtm, recaptcha
```

#### Loại trừ Styles
Danh sách handle hoặc URL cách nhau bằng dấu phẩy:
```
admin-bar, dashicons, custom-style
```

## 🎯 Hướng dẫn sử dụng

### Bước 1: Cấu hình cơ bản
1. Kích hoạt plugin
2. Vào **Settings > RB Optimizer**
3. Bật các tùy chọn:
   - ✅ Defer JavaScript
   - ✅ Async CSS

### Bước 2: Test website
1. Kiểm tra tất cả trang quan trọng
2. Test chức năng giỏ hàng, checkout
3. Kiểm tra forms, sliders, popup

### Bước 3: Xử lý lỗi (nếu có)
Nếu gặp lỗi JavaScript:
1. Bật "Loại trừ jQuery (Toàn site)" hoặc
2. Thêm URL cụ thể vào "Loại trừ jQuery theo URL"
3. Thêm script handle vào "Loại trừ Scripts"

### Bước 4: Tối ưu Fonts
#### Sử dụng Local Fonts (Khuyến nghị)
1. Tạo thư mục `/wp-content/fonts/`
2. Upload fonts theo cấu trúc:
```
wp-content/fonts/
├── lato/
│   ├── lato-regular.woff2
│   ├── lato-bold.woff2
│   └── ...
├── roboto/
│   └── ...
└── dancing-script/
    └── ...
```
3. Plugin sẽ tự động:
   - Preload local fonts
   - Disable Google Fonts tương ứng
   - Cải thiện tốc độ tải trang

### Bước 5: Kiểm tra kết quả
1. Xóa cache website
2. Test với [PageSpeed Insights](https://pagespeed.web.dev/)
3. Kiểm tra tab Network trong DevTools

## 🔍 Tương thích

### Themes
- ✅ Flatsome (Tối ưu đặc biệt)
- ✅ Các theme WordPress khác
- ✅ Theme tùy chỉnh

### Plugins
- ✅ WooCommerce
- ✅ Contact Form 7
- ✅ Yoast SEO
- ✅ Các cache plugins (WP Rocket, W3 Total Cache, etc.)
- ✅ Hầu hết các plugin phổ biến

### WordPress
- Yêu cầu: WordPress 5.0 trở lên
- PHP: 7.0 trở lên (Khuyến nghị 7.4+)

## 💡 Tips & Best Practices

### Kết hợp với Cache
- Sử dụng cùng WP Rocket hoặc W3 Total Cache
- Xóa cache sau mỗi lần thay đổi cài đặt
- Kết hợp với CDN để tối ưu tốt nhất

### Tối ưu Fonts
1. **Ưu tiên Local Fonts**:
   - Tốc độ nhanh hơn
   - Không phụ thuộc Google
   - GDPR friendly

2. **Giảm số lượng fonts**:
   - Chỉ dùng 2-3 font families
   - Giới hạn font weights (regular, bold)

3. **Font formats**:
   - Ưu tiên WOFF2 (modern browsers)
   - WOFF làm fallback

### Debug & Troubleshooting
- Kiểm tra Console cho JavaScript errors
- Sử dụng Network tab để xem loading order
- Test trên nhiều browsers khác nhau
- Tạm thời tắt plugin khác để isolate issues

## 📊 Hiệu quả

### Trước khi sử dụng
- ❌ Render Blocking Resources: 10-15 items
- ❌ PageSpeed Mobile: 40-60
- ❌ First Contentful Paint: 3-4s

### Sau khi sử dụng
- ✅ Render Blocking Resources: 2-3 items
- ✅ PageSpeed Mobile: 70-90+
- ✅ First Contentful Paint: 1-2s

## 🐛 Xử lý sự cố

### JavaScript không hoạt động
**Giải pháp:**
1. Bật "Loại trừ jQuery (Toàn site)"
2. Hoặc thêm URL vào "Loại trừ jQuery theo URL"
3. Xóa cache và test lại

### Slider/Carousel lỗi
**Giải pháp:**
- Thêm script handle vào "Loại trừ Scripts"
- Ví dụ: `swiper, slick, owl-carousel`

### Checkout page lỗi
**Giải pháp:**
- Thêm `/checkout` vào "Loại trừ jQuery theo URL"
- WooCommerce scripts sẽ load bình thường

### CSS hiển thị sai
**Giải pháp:**
- Thêm CSS handle vào "Loại trừ Styles"
- Kiểm tra Critical CSS có được bảo vệ không

## 🔐 Bảo mật

- ✅ Sanitize tất cả user inputs
- ✅ Escape outputs
- ✅ Nonce verification
- ✅ Capability checks
- ✅ No direct file access

## 📝 Changelog

### Version 1.0.0
- ✨ Initial release
- ⚡ Defer JavaScript
- 🎨 Async CSS loading
- 🔤 Font optimization (Google + Local)
- 📋 Critical CSS protection
- ⚙️ Flexible exclusion rules
- 🎯 jQuery exclusion by URL
- 🚀 Resource hints & preloading

## 👨‍💻 Đóng góp

Contributions, issues và feature requests đều được chào đón!

## 📄 License

GPL v2 or later

## 🙏 Credits

Phát triển bởi [Your Name]

## 📧 Liên hệ

- Website: https://yoursite.com
- Email: your-email@example.com

---

**⚠️ Lưu ý quan trọng:**
- Luôn backup website trước khi cài đặt
- Test kỹ trên staging trước khi lên production
- Xóa cache sau mỗi thay đổi cài đặt
- Giữ plugin và WordPress luôn được cập nhật

**💪 Kết quả:** Website nhanh hơn, điểm PageSpeed cao hơn, trải nghiệm người dùng tốt hơn!
