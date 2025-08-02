# 📦 CourierPro - Modern Courier Management System

**CourierPro** is a comprehensive and modern courier management system designed to streamline logistics operations. Built with PHP, MySQL, and Bootstrap 5, it offers robust features for package tracking, courier management, and customer service with a focus on Pakistan-based operations.

🔗 **Live Preview** 🌐 View Project: [CourierPro System](https://github.com/Rizviblue/Pro-Courier)

## 🚀 Features

### 🎯 Core Functionality
- **Multi-Role System**: Admin, Agent, and User dashboards with specific permissions
- **Package Management**: Complete tracking from pickup to delivery
- **Real-time Tracking**: Live updates on package status and delivery progress
- **Price Calculation**: Dynamic pricing based on courier type, weight, and distance
- **Pakistan Localization**: Cities, currency (PKR), and local data integration

### 🛍️ User Features
- **Track Packages**: Real-time package tracking with timeline view
- **My Packages**: View and manage personal shipments
- **Add Couriers**: Create new shipments with Pakistan cities
- **Print Tracking**: Generate printable tracking reports

### 📊 Admin Features
- **Dashboard Analytics**: Comprehensive statistics and performance metrics
- **Agent Management**: Assign and manage delivery agents
- **Customer Management**: Complete customer database and history
- **Reports & Analytics**: Detailed reports with export functionality
- **Courier Management**: Full CRUD operations for shipments

### 🔧 Agent Features
- **Assigned Packages**: View and manage assigned deliveries
- **Status Updates**: Real-time status updates for packages
- **Performance Tracking**: Monitor delivery performance and statistics

## 🛠️ Tech Stack

- **Backend**: PHP 8.0+
- **Database**: MySQL 10.4+
- **Frontend**: HTML5, CSS3, JavaScript
- **Framework**: Bootstrap 5
- **Icons**: Font Awesome 6
- **Server**: Apache/XAMPP

## 📂 How to Run

### Prerequisites
- XAMPP or similar local server
- PHP 8.0 or higher
- MySQL 10.4 or higher

### Installation Steps

1. **Clone the repository:**
   ```bash
   git clone https://github.com/Rizviblue/Pro-Courier.git
   ```

2. **Set up the database:**
   - Import `database/courier_management_system.sql` to your MySQL server
   - Configure database connection in `includes/db_connect.php`

3. **Configure the system:**
   - Place files in your XAMPP `htdocs` folder
   - Start Apache and MySQL services

4. **Access the system:**
   - Navigate to `http://localhost/Procourier/`
   - Use admin credentials: `admin@courierpro.pk` / `admin123`

## 🗂️ Project Structure

```
Procourier/
├── admin/                    # Admin dashboard and management
│   ├── dashboard.php
│   ├── add_courier.php
│   ├── courier_list.php
│   ├── agent_management.php
│   ├── customer_management.php
│   ├── reports.php
│   ├── analytics.php
│   └── generate_pdf.php
├── agent/                    # Agent dashboard and tools
│   ├── dashboard.php
│   ├── add_courier.php
│   ├── courier_list.php
│   └── reports.php
├── user/                     # User dashboard and features
│   ├── dashboard.php
│   ├── add_courier.php
│   ├── my_packages.php
│   └── track_package.php
├── includes/                 # Shared components
│   ├── db_connect.php
│   ├── header.php
│   ├── footer.php
│   ├── sidebar_admin.php
│   ├── sidebar_agent.php
│   ├── sidebar_user.php
│   └── top_bar.php
├── assets/                   # Static assets
│   ├── css/
│   │   ├── style.css
│   │   └── dark-mode.css
│   └── js/
├── database/                 # Database files
│   └── courier_management_system.sql
├── auth/                     # Authentication
│   ├── login.php
│   └── register.php
├── index.php                 # Main entry point
├── contact.php               # Contact page
└── logout.php               # Logout functionality
```

## 🎯 Key Features Explained

### 📦 Package Tracking System
- **Real-time Updates**: Live tracking with status timeline
- **QR Code Support**: Generate QR codes for package identification
- **Print Functionality**: Export tracking details as PDF
- **Mobile Responsive**: Works seamlessly on all devices

### 💰 Pricing System
- **Dynamic Calculation**: Based on courier type, weight, and distance
- **Pakistan Rates**: Localized pricing in PKR
- **Multiple Types**: Standard, Express, Overnight, Same-day delivery
- **Package Value**: Declared value for insurance purposes

### 📊 Analytics & Reporting
- **Performance Metrics**: Agent and route performance analysis
- **Revenue Tracking**: Monthly and yearly revenue reports
- **Export Options**: PDF, CSV, and JSON export formats
- **Real-time Charts**: Interactive charts using Chart.js

### 🔐 Security Features
- **Role-based Access**: Different permissions for Admin, Agent, User
- **Session Management**: Secure login/logout system
- **Data Validation**: Input sanitization and validation
- **SQL Injection Protection**: Prepared statements for all queries

## 🌍 Pakistan Localization

### 🏙️ Cities & Locations
- **50+ Pakistan Cities**: Karachi, Lahore, Islamabad, etc.
- **Province-wise Organization**: Sindh, Punjab, KPK, Balochistan
- **Postal Codes**: Complete postal code integration

### 💱 Currency & Pricing
- **PKR Currency**: All prices in Pakistani Rupees
- **Local Pricing**: Standard (1500 PKR), Express (2500 PKR)
- **Distance-based**: Additional charges for long-distance deliveries

### 👥 User Data
- **Pakistani Names**: Local names and contact information
- **Phone Numbers**: +92 format with proper validation
- **Addresses**: Pakistan-specific address formats

## 🎓 Developed For

This project was created as a comprehensive courier management solution for Pakistan-based logistics operations, demonstrating advanced PHP development, database design, and modern web application architecture.

## 🙌 Author

**Syed Ali Abbas Rizvi**
- 📧 Email: syedaliabbas2003@yahoo.com
- 🌐 LinkedIn: [linkedin.com/in/rizviblue](https://linkedin.com/in/rizviblue)
- 🐙 GitHub: [github.com/Rizviblue](https://github.com/Rizviblue)

## ⭐ Support

If you find this project helpful, please give it a star! ⭐

## 📄 License

This project is open source and available under the [MIT License](LICENSE).

---

🙋‍♂️ **Made with ❤️ by Rizviblue!**

*Building innovative solutions for Pakistan's digital future.* 🇵🇰 
