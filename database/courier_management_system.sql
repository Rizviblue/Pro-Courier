-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 02, 2025 at 07:46 AM
-- Server version: 10.4.27-MariaDB
-- PHP Version: 8.2.0

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `courier_management_system`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `AssignCourierToAgent` (IN `p_courier_id` INT, IN `p_agent_id` INT, IN `p_assigned_by` INT, IN `p_notes` TEXT)   BEGIN
    DECLARE v_current_agent_id INT;
    
    -- Get current agent assignment
    SELECT assigned_agent_id INTO v_current_agent_id FROM couriers WHERE id = p_courier_id;
    
    -- Update courier assignment
    UPDATE couriers 
    SET assigned_agent_id = p_agent_id, updated_at = CURRENT_TIMESTAMP
    WHERE id = p_courier_id;
    
    -- Close previous assignment if exists
    IF v_current_agent_id IS NOT NULL THEN
        UPDATE courier_assignments 
        SET status = 'cancelled', notes = CONCAT(IFNULL(notes, ''), ' - Reassigned to another agent')
        WHERE courier_id = p_courier_id AND agent_id = v_current_agent_id AND status = 'active';
    END IF;
    
    -- Create new assignment record
    INSERT INTO courier_assignments (courier_id, agent_id, assigned_by, notes)
    VALUES (p_courier_id, p_agent_id, p_assigned_by, p_notes);
    
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `AutoAssignCouriers` ()   BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_courier_id INT;
    DECLARE v_pickup_city_id INT;
    DECLARE v_agent_id INT;
    
    DECLARE courier_cursor CURSOR FOR
        SELECT id, pickup_city_id 
        FROM couriers 
        WHERE assigned_agent_id IS NULL 
          AND status = 'pending'
        ORDER BY priority DESC, created_at ASC;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN courier_cursor;
    
    courier_loop: LOOP
        FETCH courier_cursor INTO v_courier_id, v_pickup_city_id;
        IF done THEN
            LEAVE courier_loop;
        END IF;
        
        -- Find available agent in the same city with auto_assign enabled
        SELECT a.id INTO v_agent_id
        FROM agents a
        JOIN users u ON a.user_id = u.id
        WHERE a.city_id = v_pickup_city_id
          AND a.availability = TRUE
          AND a.auto_assign = TRUE
          AND u.status = 'active'
          AND (
              SELECT COUNT(*) 
              FROM couriers c 
              WHERE c.assigned_agent_id = a.id 
                AND DATE(c.created_at) = CURDATE()
          ) < a.max_daily_orders
        ORDER BY a.total_couriers ASC
        LIMIT 1;
        
        -- If agent found, assign courier
        IF v_agent_id IS NOT NULL THEN
            CALL AssignCourierToAgent(v_courier_id, v_agent_id, 1, 'Auto-assigned by system');
            SET v_agent_id = NULL;
        END IF;
        
    END LOOP;
    
    CLOSE courier_cursor;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `CreateCourier` (IN `p_sender_name` VARCHAR(255), IN `p_sender_phone` VARCHAR(20), IN `p_sender_address` TEXT, IN `p_receiver_name` VARCHAR(255), IN `p_receiver_phone` VARCHAR(20), IN `p_receiver_address` TEXT, IN `p_pickup_city` VARCHAR(100), IN `p_delivery_city` VARCHAR(100), IN `p_courier_type` ENUM('standard','express','overnight','same-day'), IN `p_weight` DECIMAL(8,2), IN `p_package_value` DECIMAL(10,2), IN `p_delivery_date` DATE, IN `p_special_instructions` TEXT, IN `p_created_by` INT, IN `p_customer_id` INT, OUT `p_tracking_number` VARCHAR(50), OUT `p_courier_id` INT)   BEGIN
    DECLARE v_tracking_number VARCHAR(50);
    DECLARE v_delivery_fee DECIMAL(8,2);
    DECLARE v_pickup_city_id INT;
    DECLARE v_delivery_city_id INT;
    
    -- Generate tracking number
    SET v_tracking_number = CONCAT('CMS', LPAD(FLOOR(RAND() * 999999), 6, '0'));
    
    -- Ensure tracking number is unique
    WHILE EXISTS(SELECT 1 FROM couriers WHERE tracking_number = v_tracking_number) DO
        SET v_tracking_number = CONCAT('CMS', LPAD(FLOOR(RAND() * 999999), 6, '0'));
    END WHILE;
    
    -- Calculate delivery fee based on courier type and weight
    SET v_delivery_fee = CalculateDeliveryFee(p_courier_type, p_weight, CalculateDistance(p_pickup_city, p_delivery_city));
    
    -- Get city IDs
    SELECT id INTO v_pickup_city_id FROM cities WHERE name = p_pickup_city LIMIT 1;
    SELECT id INTO v_delivery_city_id FROM cities WHERE name = p_delivery_city LIMIT 1;
    
    -- Insert courier
    INSERT INTO couriers (
        tracking_number, sender_name, sender_phone, sender_address,
        receiver_name, receiver_phone, receiver_address,
        pickup_city_id, delivery_city_id, pickup_city, delivery_city,
        courier_type, weight, package_value, delivery_fee,
        delivery_date, special_instructions, created_by, customer_id
    ) VALUES (
        v_tracking_number, p_sender_name, p_sender_phone, p_sender_address,
        p_receiver_name, p_receiver_phone, p_receiver_address,
        v_pickup_city_id, v_delivery_city_id, p_pickup_city, p_delivery_city,
        p_courier_type, p_weight, p_package_value, v_delivery_fee,
        p_delivery_date, p_special_instructions, p_created_by, p_customer_id
    );
    
    SET p_courier_id = LAST_INSERT_ID();
    SET p_tracking_number = v_tracking_number;
    
    -- Add initial tracking entry
    INSERT INTO courier_tracking_history (courier_id, status, location, description, updated_by)
    VALUES (p_courier_id, 'pending', p_pickup_city, 'Package received at origin facility', p_created_by);
    
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `GenerateTrackingNumber` (OUT `p_tracking_number` VARCHAR(50))   BEGIN
    DECLARE v_tracking_number VARCHAR(50);
    
    -- Generate tracking number
    SET v_tracking_number = CONCAT('CMS', LPAD(FLOOR(RAND() * 999999), 6, '0'));
    
    -- Ensure tracking number is unique
    WHILE EXISTS(SELECT 1 FROM couriers WHERE tracking_number = v_tracking_number) DO
        SET v_tracking_number = CONCAT('CMS', LPAD(FLOOR(RAND() * 999999), 6, '0'));
    END WHILE;
    
    SET p_tracking_number = v_tracking_number;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `GetAgentPerformanceReport` (IN `p_agent_id` INT, IN `p_date_from` DATE, IN `p_date_to` DATE)   BEGIN
    SELECT 
        a.agent_code,
        u.name as agent_name,
        COUNT(c.id) as total_couriers,
        COUNT(CASE WHEN c.status = 'delivered' THEN 1 END) as delivered_couriers,
        COUNT(CASE WHEN c.status = 'in_transit' THEN 1 END) as in_transit_couriers,
        COUNT(CASE WHEN c.status = 'pending' THEN 1 END) as pending_couriers,
        COUNT(CASE WHEN c.status = 'cancelled' THEN 1 END) as cancelled_couriers,
        ROUND(AVG(c.delivery_fee), 2) as avg_delivery_fee,
        ROUND(SUM(c.delivery_fee), 2) as total_revenue,
        ROUND(
            (COUNT(CASE WHEN c.status = 'delivered' THEN 1 END) * 100.0 / 
             NULLIF(COUNT(CASE WHEN c.status != 'pending' THEN 1 END), 0)), 2
        ) as success_rate,
        COUNT(CASE WHEN c.status = 'delivered' AND c.actual_delivery_date <= c.delivery_date THEN 1 END) as on_time_deliveries,
        ROUND(
            (COUNT(CASE WHEN c.status = 'delivered' AND c.actual_delivery_date <= c.delivery_date THEN 1 END) * 100.0 / 
             NULLIF(COUNT(CASE WHEN c.status = 'delivered' THEN 1 END), 0)), 2
        ) as on_time_percentage
    FROM agents a
    JOIN users u ON a.user_id = u.id
    LEFT JOIN couriers c ON a.id = c.assigned_agent_id 
        AND c.created_at BETWEEN p_date_from AND DATE_ADD(p_date_to, INTERVAL 1 DAY)
    WHERE a.id = p_agent_id
    GROUP BY a.id, a.agent_code, u.name;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `GetDailyStatistics` (IN `p_date` DATE)   BEGIN
    SELECT 
        p_date as report_date,
        COUNT(*) as total_couriers,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_couriers,
        COUNT(CASE WHEN status = 'in_transit' THEN 1 END) as in_transit_couriers,
        COUNT(CASE WHEN status = 'delivered' THEN 1 END) as delivered_couriers,
        COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_couriers,
        ROUND(AVG(weight), 2) as avg_weight,
        ROUND(AVG(delivery_fee), 2) as avg_delivery_fee,
        ROUND(SUM(delivery_fee), 2) as total_revenue,
        COUNT(DISTINCT assigned_agent_id) as active_agents,
        COUNT(DISTINCT customer_id) as active_customers
    FROM couriers
    WHERE DATE(created_at) = p_date;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `SearchCouriers` (IN `p_search_term` VARCHAR(255), IN `p_status` VARCHAR(50), IN `p_courier_type` VARCHAR(50), IN `p_date_from` DATE, IN `p_date_to` DATE, IN `p_limit` INT, IN `p_offset` INT)   BEGIN
    SET @sql = 'SELECT c.*, u.name as created_by_name, au.name as agent_name 
                FROM couriers c 
                LEFT JOIN users u ON c.created_by = u.id 
                LEFT JOIN agents a ON c.assigned_agent_id = a.id 
                LEFT JOIN users au ON a.user_id = au.id 
                WHERE 1=1';
    
    IF p_search_term IS NOT NULL AND p_search_term != '' THEN
        SET @sql = CONCAT(@sql, ' AND (c.tracking_number LIKE "%', p_search_term, '%" 
                                 OR c.sender_name LIKE "%', p_search_term, '%" 
                                 OR c.receiver_name LIKE "%', p_search_term, '%" 
                                 OR c.pickup_city LIKE "%', p_search_term, '%" 
                                 OR c.delivery_city LIKE "%', p_search_term, '%")');
    END IF;
    
    IF p_status IS NOT NULL AND p_status != 'all' THEN
        SET @sql = CONCAT(@sql, ' AND c.status = "', p_status, '"');
    END IF;
    
    IF p_courier_type IS NOT NULL AND p_courier_type != 'all' THEN
        SET @sql = CONCAT(@sql, ' AND c.courier_type = "', p_courier_type, '"');
    END IF;
    
    IF p_date_from IS NOT NULL THEN
        SET @sql = CONCAT(@sql, ' AND DATE(c.created_at) >= "', p_date_from, '"');
    END IF;
    
    IF p_date_to IS NOT NULL THEN
        SET @sql = CONCAT(@sql, ' AND DATE(c.created_at) <= "', p_date_to, '"');
    END IF;
    
    SET @sql = CONCAT(@sql, ' ORDER BY c.created_at DESC');
    
    IF p_limit IS NOT NULL THEN
        SET @sql = CONCAT(@sql, ' LIMIT ', p_limit);
        IF p_offset IS NOT NULL THEN
            SET @sql = CONCAT(@sql, ' OFFSET ', p_offset);
        END IF;
    END IF;
    
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `UpdateCourierStatus` (IN `p_courier_id` INT, IN `p_status` ENUM('pending','picked_up','in_transit','out_for_delivery','delivered','failed_delivery','cancelled'), IN `p_location` VARCHAR(255), IN `p_description` TEXT, IN `p_updated_by` INT)   BEGIN
    DECLARE v_courier_status ENUM('pending', 'in_transit', 'delivered', 'cancelled');
    
    -- Map tracking status to courier status
    CASE p_status
        WHEN 'pending' THEN SET v_courier_status = 'pending';
        WHEN 'picked_up' THEN SET v_courier_status = 'in_transit';
        WHEN 'in_transit' THEN SET v_courier_status = 'in_transit';
        WHEN 'out_for_delivery' THEN SET v_courier_status = 'in_transit';
        WHEN 'delivered' THEN SET v_courier_status = 'delivered';
        WHEN 'failed_delivery' THEN SET v_courier_status = 'in_transit';
        WHEN 'cancelled' THEN SET v_courier_status = 'cancelled';
        ELSE SET v_courier_status = 'pending';
    END CASE;
    
    -- Update courier status
    UPDATE couriers 
    SET status = v_courier_status,
        actual_delivery_date = CASE WHEN p_status = 'delivered' THEN CURDATE() ELSE actual_delivery_date END,
        updated_at = CURRENT_TIMESTAMP
    WHERE id = p_courier_id;
    
    -- Add tracking history entry
    INSERT INTO courier_tracking_history (courier_id, status, location, description, updated_by)
    VALUES (p_courier_id, p_status, p_location, p_description, p_updated_by);
    
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `UpdateCustomerStatistics` (IN `p_customer_id` INT)   BEGIN
    UPDATE customers c
    SET 
        total_orders = (
            SELECT COUNT(*) 
            FROM couriers co 
            WHERE co.customer_id = c.id
        ),
        total_spent = (
            SELECT IFNULL(SUM(delivery_fee), 0) 
            FROM couriers co 
            WHERE co.customer_id = c.id 
              AND co.status = 'delivered'
        ),
        last_order_date = (
            SELECT MAX(DATE(created_at)) 
            FROM couriers co 
            WHERE co.customer_id = c.id
        )
    WHERE c.id = p_customer_id;
END$$

--
-- Functions
--
CREATE DEFINER=`root`@`localhost` FUNCTION `CalculateDeliveryDays` (`p_courier_type` ENUM('standard','express','overnight','same-day'), `p_pickup_city` VARCHAR(100), `p_delivery_city` VARCHAR(100)) RETURNS INT(11) DETERMINISTIC READS SQL DATA BEGIN
    DECLARE v_base_days INT;
    DECLARE v_distance_factor INT DEFAULT 0;
    
    -- Base delivery days by courier type
    CASE p_courier_type
        WHEN 'same-day' THEN SET v_base_days = 0;
        WHEN 'overnight' THEN SET v_base_days = 1;
        WHEN 'express' THEN SET v_base_days = 2;
        WHEN 'standard' THEN SET v_base_days = 5;
        ELSE SET v_base_days = 5;
    END CASE;
    
    -- Add extra days for cross-country shipments (simplified logic)
    IF (p_pickup_city IN ('New York', 'Boston', 'Philadelphia') AND p_delivery_city IN ('Los Angeles', 'San Francisco', 'Seattle')) OR
       (p_pickup_city IN ('Los Angeles', 'San Francisco', 'Seattle') AND p_delivery_city IN ('New York', 'Boston', 'Philadelphia')) THEN
        SET v_distance_factor = 1;
    END IF;
    
    -- Same-day delivery only available within same city
    IF p_courier_type = 'same-day' AND p_pickup_city != p_delivery_city THEN
        SET v_base_days = 1; -- Convert to overnight
    END IF;
    
    RETURN v_base_days + v_distance_factor;
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `CalculateDeliveryFee` (`p_courier_type` ENUM('standard','express','overnight','same-day'), `p_weight` DECIMAL(8,2), `p_distance` INT) RETURNS DECIMAL(8,2) DETERMINISTIC READS SQL DATA BEGIN
    DECLARE v_base_fee DECIMAL(8,2);
    DECLARE v_weight_fee DECIMAL(8,2) DEFAULT 0;
    DECLARE v_distance_fee DECIMAL(8,2) DEFAULT 0;
    DECLARE v_total_fee DECIMAL(8,2);
    
    -- Base fee by courier type (USD pricing)
    CASE p_courier_type
        WHEN 'standard' THEN SET v_base_fee = 15.99;
        WHEN 'express' THEN SET v_base_fee = 25.99;
        WHEN 'overnight' THEN SET v_base_fee = 45.99;
        WHEN 'same-day' THEN SET v_base_fee = 65.99;
        ELSE SET v_base_fee = 15.99;
    END CASE;
    
    -- Additional weight fee (for packages over 5kg)
    IF p_weight > 5.0 THEN
        SET v_weight_fee = (p_weight - 5.0) * 2.50;
    END IF;
    
    -- Additional distance fee (for distances over 500km)
    IF p_distance > 500 THEN
        SET v_distance_fee = (p_distance - 500) * 0.05;
    END IF;
    
    SET v_total_fee = v_base_fee + v_weight_fee + v_distance_fee;
    
    RETURN ROUND(v_total_fee, 2);
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `CalculateDistance` (`p_city1` VARCHAR(100), `p_city2` VARCHAR(100)) RETURNS INT(11) DETERMINISTIC READS SQL DATA BEGIN
    DECLARE v_distance INT DEFAULT 500; -- Default distance
    
    -- Simplified distance calculation for Pakistan cities (in km)
    CASE
        WHEN (p_city1 = 'Karachi' AND p_city2 = 'Lahore') OR (p_city1 = 'Lahore' AND p_city2 = 'Karachi') THEN SET v_distance = 1200;
        WHEN (p_city1 = 'Karachi' AND p_city2 = 'Islamabad') OR (p_city1 = 'Islamabad' AND p_city2 = 'Karachi') THEN SET v_distance = 1400;
        WHEN (p_city1 = 'Lahore' AND p_city2 = 'Islamabad') OR (p_city1 = 'Islamabad' AND p_city2 = 'Lahore') THEN SET v_distance = 300;
        WHEN (p_city1 = 'Lahore' AND p_city2 = 'Faisalabad') OR (p_city1 = 'Faisalabad' AND p_city2 = 'Lahore') THEN SET v_distance = 120;
        WHEN (p_city1 = 'Karachi' AND p_city2 = 'Hyderabad') OR (p_city1 = 'Hyderabad' AND p_city2 = 'Karachi') THEN SET v_distance = 150;
        WHEN p_city1 = p_city2 THEN SET v_distance = 0;
        ELSE SET v_distance = 500; -- Default for unknown routes
    END CASE;
    
    RETURN v_distance;
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `FormatTrackingStatus` (`p_status` ENUM('pending','picked_up','in_transit','out_for_delivery','delivered','failed_delivery','cancelled')) RETURNS VARCHAR(50) CHARSET utf8mb4 COLLATE utf8mb4_general_ci DETERMINISTIC READS SQL DATA BEGIN
    CASE p_status
        WHEN 'pending' THEN RETURN 'Package Received';
        WHEN 'picked_up' THEN RETURN 'Picked Up';
        WHEN 'in_transit' THEN RETURN 'In Transit';
        WHEN 'out_for_delivery' THEN RETURN 'Out for Delivery';
        WHEN 'delivered' THEN RETURN 'Delivered';
        WHEN 'failed_delivery' THEN RETURN 'Delivery Failed';
        WHEN 'cancelled' THEN RETURN 'Cancelled';
        ELSE RETURN 'Unknown Status';
    END CASE;
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `GetAgentWorkload` (`p_agent_id` INT, `p_date` DATE) RETURNS INT(11) DETERMINISTIC READS SQL DATA BEGIN
    DECLARE v_workload INT DEFAULT 0;
    
    SELECT COUNT(*) INTO v_workload
    FROM couriers
    WHERE assigned_agent_id = p_agent_id
      AND DATE(created_at) = p_date
      AND status IN ('pending', 'in_transit');
    
    RETURN v_workload;
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `GetBusinessDays` (`p_start_date` DATE, `p_end_date` DATE) RETURNS INT(11) DETERMINISTIC READS SQL DATA BEGIN
    DECLARE v_days INT DEFAULT 0;
    DECLARE v_current_date DATE;
    
    SET v_current_date = p_start_date;
    
    WHILE v_current_date <= p_end_date DO
        -- Count only weekdays (Monday = 2, Friday = 6)
        IF DAYOFWEEK(v_current_date) BETWEEN 2 AND 6 THEN
            SET v_days = v_days + 1;
        END IF;
        SET v_current_date = DATE_ADD(v_current_date, INTERVAL 1 DAY);
    END WHILE;
    
    RETURN v_days;
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `GetCourierStatusDisplay` (`p_status` ENUM('pending','in_transit','delivered','cancelled')) RETURNS VARCHAR(50) CHARSET utf8mb4 COLLATE utf8mb4_general_ci DETERMINISTIC READS SQL DATA BEGIN
    CASE p_status
        WHEN 'pending' THEN RETURN 'Pending Pickup';
        WHEN 'in_transit' THEN RETURN 'In Transit';
        WHEN 'delivered' THEN RETURN 'Delivered';
        WHEN 'cancelled' THEN RETURN 'Cancelled';
        ELSE RETURN 'Unknown Status';
    END CASE;
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `GetCustomerTier` (`p_customer_id` INT) RETURNS VARCHAR(20) CHARSET utf8mb4 COLLATE utf8mb4_general_ci DETERMINISTIC READS SQL DATA BEGIN
    DECLARE v_total_spent DECIMAL(10,2);
    DECLARE v_tier VARCHAR(20);
    
    SELECT total_spent INTO v_total_spent
    FROM customers
    WHERE id = p_customer_id;
    
    CASE
        WHEN v_total_spent >= 10000 THEN SET v_tier = 'Platinum';
        WHEN v_total_spent >= 5000 THEN SET v_tier = 'Gold';
        WHEN v_total_spent >= 1000 THEN SET v_tier = 'Silver';
        ELSE SET v_tier = 'Bronze';
    END CASE;
    
    RETURN v_tier;
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `GetEstimatedDeliveryDate` (`p_courier_type` ENUM('standard','express','overnight','same-day'), `p_pickup_city` VARCHAR(100), `p_delivery_city` VARCHAR(100), `p_pickup_date` DATE) RETURNS DATE DETERMINISTIC READS SQL DATA BEGIN
    DECLARE v_delivery_days INT;
    DECLARE v_estimated_date DATE;
    
    SET v_delivery_days = CalculateDeliveryDays(p_courier_type, p_pickup_city, p_delivery_city);
    
    -- Add business days (skip weekends for standard delivery)
    IF p_courier_type = 'standard' THEN
        SET v_estimated_date = p_pickup_date;
        WHILE v_delivery_days > 0 DO
            SET v_estimated_date = DATE_ADD(v_estimated_date, INTERVAL 1 DAY);
            -- Skip weekends
            IF DAYOFWEEK(v_estimated_date) NOT IN (1, 7) THEN
                SET v_delivery_days = v_delivery_days - 1;
            END IF;
        END WHILE;
    ELSE
        SET v_estimated_date = DATE_ADD(p_pickup_date, INTERVAL v_delivery_days DAY);
    END IF;
    
    RETURN v_estimated_date;
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `IsAgentAvailable` (`p_agent_id` INT, `p_date` DATE) RETURNS TINYINT(1) DETERMINISTIC READS SQL DATA BEGIN
    DECLARE v_is_available BOOLEAN DEFAULT FALSE;
    DECLARE v_current_workload INT;
    DECLARE v_max_orders INT;
    DECLARE v_availability BOOLEAN;
    DECLARE v_user_status VARCHAR(20);
    
    -- Get agent details
    SELECT a.availability, a.max_daily_orders, u.status
    INTO v_availability, v_max_orders, v_user_status
    FROM agents a
    JOIN users u ON a.user_id = u.id
    WHERE a.id = p_agent_id;
    
    -- Check if agent is active and available
    IF v_availability = TRUE AND v_user_status = 'active' THEN
        -- Get current workload
        SET v_current_workload = GetAgentWorkload(p_agent_id, p_date);
        
        -- Check if under max daily orders
        IF v_current_workload < v_max_orders THEN
            SET v_is_available = TRUE;
        END IF;
    END IF;
    
    RETURN v_is_available;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `agents`
--

CREATE TABLE `agents` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `agent_code` varchar(20) DEFAULT NULL,
  `city_id` int(11) DEFAULT NULL,
  `working_hours` varchar(50) DEFAULT '9to5',
  `max_daily_orders` int(11) DEFAULT 20,
  `availability` tinyint(1) DEFAULT 1,
  `auto_assign` tinyint(1) DEFAULT 0,
  `total_couriers` int(11) DEFAULT 0,
  `success_rate` decimal(5,2) DEFAULT 0.00,
  `rating` decimal(3,2) DEFAULT 0.00,
  `joined_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `agents`
--

INSERT INTO `agents` (`id`, `user_id`, `agent_code`, `city_id`, `working_hours`, `max_daily_orders`, `availability`, `auto_assign`, `total_couriers`, `success_rate`, `rating`, `joined_date`, `created_at`, `updated_at`) VALUES
(1, 3, 'AGT001', 1, '9to5', 25, 1, 0, 161, '94.50', '4.80', '2023-01-15', '2025-07-23 11:13:52', '2025-07-23 11:13:52'),
(2, 4, 'AGT002', 2, '8to6', 30, 1, 1, 139, '92.30', '4.70', '2023-02-20', '2025-07-23 11:13:52', '2025-07-23 11:13:52'),
(3, 5, 'AGT003', 3, '9to5', 20, 0, 0, 89, '88.20', '4.60', '2023-03-10', '2025-07-23 11:13:52', '2025-07-23 11:13:52'),
(4, 6, 'AGT004', 4, '7to7', 35, 1, 1, 203, '96.10', '4.90', '2022-11-05', '2025-07-23 11:13:52', '2025-07-23 11:13:52'),
(5, 7, 'AGT005', 5, '9to5', 22, 1, 0, 81, '91.40', '4.65', '2023-04-12', '2025-07-23 11:13:52', '2025-07-23 11:13:52'),
(6, 8, 'AGT006', 6, '8to6', 28, 1, 1, 113, '93.80', '4.75', '2023-05-18', '2025-07-23 11:13:52', '2025-07-23 11:13:52');

-- --------------------------------------------------------

--
-- Stand-in structure for view `agent_performance`
-- (See below for the actual view)
--
CREATE TABLE `agent_performance` (
`agent_id` int(11)
,`agent_code` varchar(20)
,`agent_name` varchar(255)
,`agent_email` varchar(255)
,`city_name` varchar(100)
,`total_couriers` int(11)
,`success_rate` decimal(5,2)
,`rating` decimal(3,2)
,`availability` tinyint(1)
,`max_daily_orders` int(11)
,`total_assigned_couriers` bigint(21)
,`delivered_couriers` bigint(21)
,`in_transit_couriers` bigint(21)
,`pending_couriers` bigint(21)
,`cancelled_couriers` bigint(21)
,`actual_success_rate` decimal(26,2)
,`avg_delivery_fee` decimal(12,6)
,`total_revenue` decimal(30,2)
,`on_time_deliveries` bigint(21)
,`on_time_percentage` decimal(26,2)
);

-- --------------------------------------------------------

--
-- Table structure for table `cities`
--

CREATE TABLE `cities` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `state` varchar(50) DEFAULT NULL,
  `country` varchar(50) DEFAULT 'USA',
  `postal_code` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cities`
--

INSERT INTO `cities` (`id`, `name`, `state`, `country`, `postal_code`, `created_at`) VALUES
(1, 'Karachi', 'Sindh', 'Pakistan', '74000', '2025-07-23 11:13:52'),
(2, 'Lahore', 'Punjab', 'Pakistan', '54000', '2025-07-23 11:13:52'),
(3, 'Faisalabad', 'Punjab', 'Pakistan', '38000', '2025-07-23 11:13:52'),
(4, 'Rawalpindi', 'Punjab', 'Pakistan', '46000', '2025-07-23 11:13:52'),
(5, 'Multan', 'Punjab', 'Pakistan', '60000', '2025-07-23 11:13:52'),
(6, 'Hyderabad', 'Sindh', 'Pakistan', '71000', '2025-07-23 11:13:52'),
(7, 'Gujranwala', 'Punjab', 'Pakistan', '52250', '2025-07-23 11:13:52'),
(8, 'Peshawar', 'Khyber Pakhtunkhwa', 'Pakistan', '25000', '2025-07-23 11:13:52'),
(9, 'Quetta', 'Balochistan', 'Pakistan', '87300', '2025-07-23 11:13:52'),
(10, 'Islamabad', 'Federal Territory', 'Pakistan', '44000', '2025-07-23 11:13:52'),
(11, 'Bahawalpur', 'Punjab', 'Pakistan', '63100', '2025-07-23 11:13:52'),
(12, 'Sargodha', 'Punjab', 'Pakistan', '40100', '2025-07-23 11:13:52'),
(13, 'Sialkot', 'Punjab', 'Pakistan', '51310', '2025-07-23 11:13:52'),
(14, 'Sukkur', 'Sindh', 'Pakistan', '65200', '2025-07-23 11:13:52'),
(15, 'Larkana', 'Sindh', 'Pakistan', '77150', '2025-07-23 11:13:52'),
(16, 'Sheikhupura', 'Punjab', 'Pakistan', '39350', '2025-07-23 11:13:52'),
(17, 'Mardan', 'Khyber Pakhtunkhwa', 'Pakistan', '23200', '2025-07-23 11:13:52'),
(18, 'Gujrat', 'Punjab', 'Pakistan', '50700', '2025-07-23 11:13:52'),
(19, 'Kasur', 'Punjab', 'Pakistan', '55050', '2025-07-23 11:13:52'),
(20, 'Dera Ghazi Khan', 'Punjab', 'Pakistan', '32200', '2025-07-23 11:13:52'),
(21, 'Sahiwal', 'Punjab', 'Pakistan', '57000', '2025-07-23 11:13:52'),
(22, 'Nawabshah', 'Sindh', 'Pakistan', '67450', '2025-07-23 11:13:52'),
(23, 'Mingora', 'Khyber Pakhtunkhwa', 'Pakistan', '19130', '2025-07-23 11:13:52'),
(24, 'Okara', 'Punjab', 'Pakistan', '56300', '2025-07-23 11:13:52'),
(25, 'Mirpur Khas', 'Sindh', 'Pakistan', '69000', '2025-07-23 11:13:52'),
(26, 'Chiniot', 'Punjab', 'Pakistan', '35400', '2025-07-23 11:13:52'),
(27, 'Kamoke', 'Punjab', 'Pakistan', '52350', '2025-07-23 11:13:52'),
(28, 'Hafizabad', 'Punjab', 'Pakistan', '52110', '2025-07-23 11:13:52'),
(29, 'Sadiqabad', 'Punjab', 'Pakistan', '64350', '2025-07-23 11:13:52'),
(30, 'Burewala', 'Punjab', 'Pakistan', '61010', '2025-07-23 11:13:52'),
(31, 'Kohat', 'Khyber Pakhtunkhwa', 'Pakistan', '26000', '2025-07-23 11:13:52'),
(32, 'Khanewal', 'Punjab', 'Pakistan', '58150', '2025-07-23 11:13:52'),
(33, 'Dera Ismail Khan', 'Khyber Pakhtunkhwa', 'Pakistan', '29050', '2025-07-23 11:13:52'),
(34, 'Turbat', 'Balochistan', 'Pakistan', '92600', '2025-07-23 11:13:52'),
(35, 'Muzaffargarh', 'Punjab', 'Pakistan', '34400', '2025-07-23 11:13:52'),
(36, 'Abbottabad', 'Khyber Pakhtunkhwa', 'Pakistan', '22010', '2025-07-23 11:13:52'),
(37, 'Faisalabad', 'Punjab', 'Pakistan', '38000', '2025-07-23 11:13:52'),
(38, 'Jhelum', 'Punjab', 'Pakistan', '49600', '2025-07-23 11:13:52'),
(39, 'Chaman', 'Balochistan', 'Pakistan', '86000', '2025-07-23 11:13:52'),
(40, 'Charsadda', 'Khyber Pakhtunkhwa', 'Pakistan', '24620', '2025-07-23 11:13:52'),
(41, 'Rahim Yar Khan', 'Punjab', 'Pakistan', '64200', '2025-07-23 11:13:52'),
(42, 'Bahawalnagar', 'Punjab', 'Pakistan', '62300', '2025-07-23 11:13:52'),
(43, 'Chakwal', 'Punjab', 'Pakistan', '48800', '2025-07-23 11:13:52'),
(44, 'Gujar Khan', 'Punjab', 'Pakistan', '47850', '2025-07-23 11:13:52'),
(45, 'Mandi Bahauddin', 'Punjab', 'Pakistan', '50400', '2025-07-23 11:13:52'),
(46, 'Sheikhupura', 'Punjab', 'Pakistan', '39350', '2025-07-23 11:13:52'),
(47, 'Vehari', 'Punjab', 'Pakistan', '61100', '2025-07-23 11:13:52'),
(48, 'Nowshera', 'Khyber Pakhtunkhwa', 'Pakistan', '24100', '2025-07-23 11:13:52'),
(49, 'Dera Bugti', 'Balochistan', 'Pakistan', '80150', '2025-07-23 11:13:52'),
(50, 'Khuzdar', 'Balochistan', 'Pakistan', '89100', '2025-07-23 11:13:52');

-- --------------------------------------------------------

--
-- Table structure for table `couriers`
--

CREATE TABLE `couriers` (
  `id` int(11) NOT NULL,
  `tracking_number` varchar(50) NOT NULL,
  `sender_name` varchar(255) NOT NULL,
  `sender_phone` varchar(20) DEFAULT NULL,
  `sender_address` text DEFAULT NULL,
  `receiver_name` varchar(255) NOT NULL,
  `receiver_phone` varchar(20) DEFAULT NULL,
  `receiver_address` text DEFAULT NULL,
  `pickup_city_id` int(11) DEFAULT NULL,
  `delivery_city_id` int(11) DEFAULT NULL,
  `pickup_city` varchar(100) NOT NULL,
  `delivery_city` varchar(100) NOT NULL,
  `courier_type` enum('standard','express','overnight','same-day') DEFAULT 'standard',
  `weight` decimal(8,2) DEFAULT 0.00,
  `dimensions` varchar(100) DEFAULT NULL,
  `package_value` decimal(10,2) DEFAULT 0.00,
  `delivery_fee` decimal(8,2) DEFAULT 0.00,
  `status` enum('pending','in_transit','delivered','cancelled') DEFAULT 'pending',
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `delivery_date` date DEFAULT NULL,
  `actual_delivery_date` date DEFAULT NULL,
  `pickup_date` date DEFAULT NULL,
  `estimated_delivery_time` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `special_instructions` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `assigned_agent_id` int(11) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `couriers`
--

INSERT INTO `couriers` (`id`, `tracking_number`, `sender_name`, `sender_phone`, `sender_address`, `receiver_name`, `receiver_phone`, `receiver_address`, `pickup_city_id`, `delivery_city_id`, `pickup_city`, `delivery_city`, `courier_type`, `weight`, `dimensions`, `package_value`, `delivery_fee`, `status`, `priority`, `delivery_date`, `actual_delivery_date`, `pickup_date`, `estimated_delivery_time`, `special_instructions`, `notes`, `created_by`, `assigned_agent_id`, `customer_id`, `created_at`, `updated_at`) VALUES
(1, 'CMS001234', 'Ahmed Khan', '+92 300 123 4567', 'House 123, Block 7, Clifton, Karachi, Sindh', 'Ali Raza', '+92 301 234 5678', 'Flat 45, Gulberg III, Lahore, Punjab', 1, 2, 'Karachi', 'Lahore', 'express', '2.50', NULL, '15000.00', '2500.00', 'in_transit', 'high', '2024-01-25', NULL, '2024-01-20', '2025-07-30 16:15:34', 'Handle with care', 'Fragile electronics', 3, 1, NULL, '2024-01-20 05:00:00', '2025-07-30 16:15:34'),
(2, 'CMS001235', 'Sadia Khan', '+92 302 345 6789', 'Flat 45, Gulberg III, Lahore, Punjab', 'Fatima Hassan', '+92 303 456 7890', 'Flat 23, Saddar, Hyderabad, Sindh', 3, 39, 'Lahore', 'Hyderabad', 'standard', '1.20', NULL, '7500.00', '1800.00', 'delivered', 'medium', '2024-01-24', NULL, '2024-01-19', '2025-07-30 16:15:34', 'None', 'Delivered successfully', 4, 2, 3, '2024-01-19 09:30:00', '2025-07-30 16:15:34'),
(3, 'CMS001236', 'Hassan Ali', '+92 304 567 8901', 'House 67, Satellite Town, Rawalpindi, Punjab', 'Amina Malik', '+92 305 678 9012', 'Shop 12, Ghanta Ghar, Faisalabad, Punjab', 4, 5, 'Rawalpindi', 'Faisalabad', 'express', '3.80', NULL, '20000.00', '1200.00', 'in_transit', 'medium', '2024-01-26', NULL, NULL, '2025-07-30 16:15:34', 'Signature required', 'Awaiting pickup', 3, 1, 5, '2024-01-21 04:15:00', '2025-07-30 16:15:34'),
(4, 'CMS001237', 'Omar Khan', '+92 306 789 0123', 'House 89, Model Town, Multan, Punjab', 'Sara Ali', '+92 307 890 1234', 'Shop 56, University Road, Peshawar, KPK', 5, 7, 'Multan', 'Peshawar', 'standard', '0.80', NULL, '4500.00', '2200.00', 'delivered', 'low', '2024-01-23', '2025-07-28', NULL, '2025-07-30 16:15:34', 'None', 'Cancelled by sender', 4, 2, 7, '2024-01-18 11:45:00', '2025-07-30 16:15:34'),
(5, 'CMS001238', 'Fatima Hassan', '+92 308 901 2345', 'Flat 23, Saddar, Hyderabad, Sindh', 'Bilal Khan', '+92 309 012 3456', 'House 78, Jinnah Road, Quetta, Balochistan', 8, 9, 'Hyderabad', 'Quetta', 'overnight', '1.50', NULL, '30000.00', '2800.00', 'in_transit', 'urgent', '2024-01-22', NULL, '2024-01-21', '2025-07-30 16:15:34', 'Next day delivery', 'Priority shipment', 6, 4, 9, '2024-01-21 03:00:00', '2025-07-30 16:15:34'),
(6, 'CMS001239', 'Jack Robinson', '+1 555 1006', '600 Tech Park, San Jose, CA', 'Kate Anderson', '+1 555 0111', '852 Valley View, Austin, TX 73301', 10, 11, 'San Jose', 'Austin', 'express', '2.20', NULL, '180.00', '28.99', 'delivered', 'high', '2024-01-20', NULL, '2024-01-18', '2025-07-23 11:13:52', 'Handle with care', 'Computer equipment', 7, 5, NULL, '2024-01-18 06:20:00', '2025-07-23 11:13:52'),
(7, 'CMS001240', 'Liam Murphy', '+1 555 1007', '700 Innovation Dr, Seattle, WA', 'Alice Cooper', '+1 555 0101', '123 Main St, New York, NY 10001', 18, 1, 'Seattle', 'New York', 'standard', '4.50', NULL, '120.00', '22.99', 'in_transit', 'medium', '2024-01-27', NULL, '2024-01-22', '2025-07-23 11:13:52', 'None', 'Cross-country shipment', 3, 1, NULL, '2024-01-22 08:45:00', '2025-07-23 11:13:52'),
(8, 'CMS001241', 'Sarah Johnson', '+1 555 1008', '800 Corporate Blvd, Denver, CO', 'Bob Johnson', '+1 555 0102', '456 Oak Ave, Los Angeles, CA 90001', 19, 2, 'Denver', 'Los Angeles', 'same-day', '0.90', NULL, '85.00', '55.99', 'pending', 'urgent', '2024-01-21', NULL, '2024-01-21', '2025-07-25 18:30:42', 'Same day delivery', 'Rush order completed', 4, 2, 2, '2024-01-21 02:30:00', '2025-07-25 18:30:42'),
(9, 'CMS001242', 'Michael Chen', '+1 555 1009', '900 Enterprise Ave, Boston, MA', 'David Wilson', '+1 555 0104', '321 Elm Dr, Houston, TX 77001', 20, 4, 'Boston', 'Houston', 'express', '3.20', NULL, '250.00', '32.99', 'pending', 'high', '2024-01-28', NULL, NULL, '2025-07-23 11:13:52', 'Fragile contents', 'Awaiting agent assignment', 6, NULL, 4, '2024-01-23 05:15:00', '2025-07-23 11:13:52'),
(10, 'CMS001243', 'Emily Rodriguez', '+1 555 1010', '1000 Gateway Plaza, Detroit, MI', 'Frank Miller', '+1 555 0106', '987 Cedar Rd, Philadelphia, PA 19101', 22, 6, 'Detroit', 'Philadelphia', 'standard', '2.80', NULL, '95.00', '18.99', 'cancelled', 'medium', '2024-01-26', NULL, '2024-01-23', '2025-07-28 17:14:35', 'None', 'Standard delivery', 7, 5, 6, '2024-01-23 09:20:00', '2025-07-28 17:14:35'),
(11, 'CMS001244', 'Lisa Wang', '+1 555 1011', '1100 Silicon Valley, San Jose, CA', 'Grace Lee', '+1 555 0107', '147 Birch St, San Antonio, TX 78201', 10, 7, 'San Jose', 'San Antonio', 'express', '1.75', NULL, '160.00', '26.99', 'delivered', 'medium', '2024-01-19', NULL, '2024-01-17', '2025-07-23 11:13:52', 'Tech equipment', 'Delivered on time', 3, 1, 7, '2024-01-17 04:30:00', '2025-07-23 11:13:52'),
(12, 'CMS001245', 'Robert Martinez', '+1 555 1012', '1200 Financial District, New York, NY', 'Henry Taylor', '+1 555 0108', '258 Spruce Ave, San Diego, CA 92101', 1, 8, 'New York', 'San Diego', 'overnight', '0.65', NULL, '400.00', '65.99', 'delivered', 'urgent', '2024-01-18', NULL, '2024-01-17', '2025-07-23 11:13:52', 'Overnight delivery', 'High-value item', 4, 2, 8, '2024-01-17 10:45:00', '2025-07-23 11:13:52'),
(13, 'CMS001246', 'Amanda Foster', '+1 555 1013', '1300 Research Park, Austin, TX', 'Ivy Chen', '+1 555 0109', '369 Willow Way, Dallas, TX 75201', 11, 9, 'Austin', 'Dallas', 'standard', '5.20', NULL, '80.00', '16.99', 'in_transit', 'low', '2024-01-25', NULL, '2024-01-22', '2025-07-23 11:13:52', 'Books and documents', 'Educational materials', 6, 4, 9, '2024-01-22 06:00:00', '2025-07-23 11:13:52'),
(14, 'CMS001247', 'Christopher Lee', '+1 555 1014', '1400 Medical Center, Houston, TX', 'Jack Robinson', '+1 555 0110', '741 Ash Blvd, San Jose, CA 95101', 4, 10, 'Houston', 'San Jose', 'express', '1.10', NULL, '500.00', '42.99', 'pending', 'urgent', '2024-01-29', NULL, NULL, '2025-07-23 11:13:52', 'Medical supplies', 'Temperature sensitive', 7, NULL, 10, '2024-01-24 03:15:00', '2025-07-23 11:13:52'),
(15, 'CMS001248', 'Jennifer Adams', '+1 555 1015', '1500 Art District, Miami, FL', 'Kate Anderson', '+1 555 0111', '852 Valley View, Austin, TX 73301', 39, 11, 'Miami', 'Austin', 'standard', '3.60', NULL, '220.00', '24.99', 'delivered', 'medium', '2024-01-21', NULL, '2024-01-19', '2025-07-23 11:13:52', 'Artwork - fragile', 'Special handling required', 3, 1, NULL, '2024-01-19 07:30:00', '2025-07-23 11:13:52'),
(16, 'CMS001249', 'Daniel Brown', '+1 555 1016', '1600 Sports Complex, Phoenix, AZ', 'Liam Murphy', '+1 555 0112', '963 Mountain View, Denver, CO 80201', 5, 19, 'Phoenix', 'Denver', 'express', '2.90', NULL, '135.00', '29.99', 'in_transit', 'medium', '2024-01-27', NULL, '2024-01-24', '2025-07-23 11:13:52', 'Sports equipment', 'Handle with care', 8, 6, NULL, '2024-01-24 05:45:00', '2025-07-23 11:13:52'),
(17, 'CMS001250', 'Michelle Garcia', '+1 555 1017', '1700 Fashion Ave, Los Angeles, CA', 'Alice Cooper', '+1 555 0101', '123 Main St, New York, NY 10001', 2, 1, 'Los Angeles', 'New York', 'overnight', '1.30', NULL, '350.00', '58.99', 'delivered', 'high', '2024-01-20', NULL, '2024-01-19', '2025-07-23 11:13:52', 'Designer clothing', 'Fashion week delivery', 4, 2, NULL, '2024-01-19 11:20:00', '2025-07-23 11:13:52'),
(18, 'CMS001251', 'Kevin Wilson', '+1 555 1018', '1800 Tech Hub, Seattle, WA', 'Carol Brown', '+1 555 0103', '789 Pine St, Chicago, IL 60601', 18, 3, 'Seattle', 'Chicago', 'standard', '4.10', NULL, '190.00', '21.99', 'cancelled', 'medium', '2024-01-23', NULL, NULL, '2025-07-23 11:13:52', 'Software package', 'Cancelled - duplicate order', 6, NULL, 3, '2024-01-20 08:10:00', '2025-07-23 11:13:52'),
(19, 'CMS001252', 'Rachel Thompson', '+1 555 1019', '1900 Green Energy, Portland, OR', 'Eva Davis', '+1 555 0105', '654 Maple Ln, Phoenix, AZ 85001', 24, 5, 'Portland', 'Phoenix', 'express', '2.40', NULL, '275.00', '33.99', 'pending', 'high', '2024-01-28', NULL, '2024-01-25', '2025-07-25 18:30:52', 'Solar equipment', 'Renewable energy parts', 7, 5, 5, '2024-01-25 04:00:00', '2025-07-25 18:30:52'),
(22, 'CMS450823', 'Sender Name Here', 'Sender Phone Here', 'Sender Address Here', 'Receiver Name Here', 'Receiver Phone Here', 'Receiver Address Here', 1, 2, 'New York', 'Los Angeles', 'standard', '2.50', NULL, '0.00', '15.99', 'pending', 'medium', '2025-08-15', NULL, NULL, '2025-07-25 20:29:57', 'Handle with care', NULL, 9, NULL, 5, '2025-07-25 20:29:57', '2025-07-25 20:29:57'),
(0, 'CMS988628', 'Talha', '0320444223', 'Karachi', 'Azhar', '03111200333', 'Islamabad', 1, 10, 'Karachi', 'Islamabad', 'overnight', '5.00', NULL, '300.00', '90.99', 'in_transit', 'medium', '2025-08-15', NULL, NULL, '2025-08-02 05:40:08', '', NULL, 9, NULL, 11, '2025-08-02 05:38:24', '2025-08-02 05:40:08');

--
-- Triggers `couriers`
--
DELIMITER $$
CREATE TRIGGER `update_agent_courier_count_insert` AFTER INSERT ON `couriers` FOR EACH ROW BEGIN
    IF NEW.assigned_agent_id IS NOT NULL THEN
        UPDATE agents 
        SET total_couriers = total_couriers + 1 
        WHERE id = NEW.assigned_agent_id;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `update_agent_courier_count_update` AFTER UPDATE ON `couriers` FOR EACH ROW BEGIN
    -- Decrease count for old agent
    IF OLD.assigned_agent_id IS NOT NULL AND OLD.assigned_agent_id != NEW.assigned_agent_id THEN
        UPDATE agents 
        SET total_couriers = total_couriers - 1 
        WHERE id = OLD.assigned_agent_id;
    END IF;
    
    -- Increase count for new agent
    IF NEW.assigned_agent_id IS NOT NULL AND OLD.assigned_agent_id != NEW.assigned_agent_id THEN
        UPDATE agents 
        SET total_couriers = total_couriers + 1 
        WHERE id = NEW.assigned_agent_id;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `update_customer_stats_insert` AFTER INSERT ON `couriers` FOR EACH ROW BEGIN
    IF NEW.customer_id IS NOT NULL THEN
        UPDATE customers 
        SET total_orders = total_orders + 1,
            last_order_date = CURDATE()
        WHERE id = NEW.customer_id;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `courier_assignments`
--

CREATE TABLE `courier_assignments` (
  `id` int(11) NOT NULL,
  `courier_id` int(11) NOT NULL,
  `agent_id` int(11) NOT NULL,
  `assigned_by` int(11) DEFAULT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('active','completed','cancelled') DEFAULT 'active',
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `courier_assignments`
--

INSERT INTO `courier_assignments` (`id`, `courier_id`, `agent_id`, `assigned_by`, `assigned_at`, `status`, `notes`) VALUES
(1, 1, 1, 1, '2024-01-20 05:30:00', 'active', 'High priority express delivery'),
(2, 2, 2, 1, '2024-01-19 10:00:00', 'completed', 'Standard delivery completed successfully'),
(3, 3, 1, 1, '2024-01-21 04:45:00', 'active', 'Awaiting pickup confirmation'),
(4, 4, 2, 1, '2024-01-18 12:00:00', 'cancelled', 'Assignment cancelled due to shipment cancellation'),
(5, 5, 4, 1, '2024-01-21 03:30:00', 'active', 'Overnight priority shipment'),
(6, 6, 5, 1, '2024-01-18 06:45:00', 'completed', 'Express delivery completed on time'),
(7, 7, 1, 1, '2024-01-22 09:00:00', 'active', 'Cross-country standard delivery'),
(8, 8, 2, 1, '2024-01-21 02:45:00', 'completed', 'Same-day delivery completed'),
(9, 10, 5, 1, '2024-01-23 09:45:00', 'active', 'Standard delivery in progress'),
(10, 11, 1, 1, '2024-01-17 05:00:00', 'completed', 'Tech equipment delivered safely'),
(11, 12, 2, 1, '2024-01-17 11:00:00', 'completed', 'Overnight high-value delivery'),
(12, 13, 4, 1, '2024-01-22 06:30:00', 'active', 'Educational materials shipment'),
(13, 15, 1, 1, '2024-01-19 07:45:00', 'completed', 'Artwork delivered with special handling'),
(14, 16, 6, 1, '2024-01-24 06:00:00', 'active', 'Sports equipment in transit'),
(15, 17, 2, 1, '2024-01-19 11:45:00', 'completed', 'Fashion week priority delivery'),
(16, 19, 5, 1, '2024-01-25 04:30:00', 'active', 'Renewable energy equipment');

-- --------------------------------------------------------

--
-- Stand-in structure for view `courier_details`
-- (See below for the actual view)
--
CREATE TABLE `courier_details` (
`id` int(11)
,`tracking_number` varchar(50)
,`sender_name` varchar(255)
,`sender_phone` varchar(20)
,`receiver_name` varchar(255)
,`receiver_phone` varchar(20)
,`pickup_city` varchar(100)
,`delivery_city` varchar(100)
,`pickup_city_full` varchar(100)
,`delivery_city_full` varchar(100)
,`courier_type` enum('standard','express','overnight','same-day')
,`weight` decimal(8,2)
,`package_value` decimal(10,2)
,`delivery_fee` decimal(8,2)
,`status` enum('pending','in_transit','delivered','cancelled')
,`priority` enum('low','medium','high','urgent')
,`delivery_date` date
,`actual_delivery_date` date
,`pickup_date` date
,`special_instructions` text
,`notes` text
,`created_by_name` varchar(255)
,`created_by_email` varchar(255)
,`agent_code` varchar(20)
,`agent_name` varchar(255)
,`agent_email` varchar(255)
,`customer_name` varchar(255)
,`customer_email` varchar(255)
,`created_at` timestamp
,`updated_at` timestamp
,`delivery_status` varchar(11)
);

-- --------------------------------------------------------

--
-- Table structure for table `courier_tracking_history`
--

CREATE TABLE `courier_tracking_history` (
  `id` int(11) NOT NULL,
  `courier_id` int(11) NOT NULL,
  `status` enum('pending','picked_up','in_transit','out_for_delivery','delivered','failed_delivery','cancelled') NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `courier_tracking_history`
--

INSERT INTO `courier_tracking_history` (`id`, `courier_id`, `status`, `location`, `description`, `updated_by`, `latitude`, `longitude`, `created_at`) VALUES
(1, 1, 'pending', 'Karachi, Sindh', 'Package received at origin facility', 3, NULL, NULL, '2024-01-20 05:00:00'),
(2, 1, 'picked_up', 'New York, NY', 'Package picked up by courier', 3, NULL, NULL, '2024-01-20 06:30:00'),
(3, 1, 'in_transit', 'Philadelphia, PA', 'Package in transit - arrived at Philadelphia hub', 3, NULL, NULL, '2024-01-20 13:45:00'),
(4, 1, 'in_transit', 'Chicago, IL', 'Package in transit - arrived at Chicago hub', 3, NULL, NULL, '2024-01-21 03:20:00'),
(5, 1, 'in_transit', 'Denver, CO', 'Package in transit - arrived at Denver hub', 3, NULL, NULL, '2024-01-21 15:15:00'),
(6, 2, 'pending', 'Lahore, Punjab', 'Package received at origin facility', 4, NULL, NULL, '2024-01-19 09:30:00'),
(7, 2, 'picked_up', 'Chicago, IL', 'Package picked up by courier', 4, NULL, NULL, '2024-01-19 11:00:00'),
(8, 2, 'in_transit', 'Atlanta, GA', 'Package in transit - arrived at Atlanta hub', 4, NULL, NULL, '2024-01-20 05:30:00'),
(9, 2, 'out_for_delivery', 'Miami, FL', 'Package out for delivery', 4, NULL, NULL, '2024-01-21 04:00:00'),
(10, 2, 'delivered', 'Miami, FL', 'Package delivered successfully', 4, NULL, NULL, '2024-01-21 09:20:00'),
(11, 3, 'pending', 'Rawalpindi, Punjab', 'Package received at origin facility', 3, NULL, NULL, '2024-01-21 04:15:00'),
(12, 4, 'pending', 'Phoenix, AZ', 'Package received at origin facility', 4, NULL, NULL, '2024-01-18 11:45:00'),
(13, 4, 'cancelled', 'Phoenix, AZ', 'Package cancelled by sender request', 4, NULL, NULL, '2024-01-18 13:00:00'),
(14, 5, 'pending', 'San Diego, CA', 'Package received at origin facility', 6, NULL, NULL, '2024-01-21 03:00:00'),
(15, 5, 'picked_up', 'San Diego, CA', 'Package picked up by courier', 6, NULL, NULL, '2024-01-21 04:30:00'),
(16, 5, 'in_transit', 'Phoenix, AZ', 'Package in transit - arrived at Phoenix hub', 6, NULL, NULL, '2024-01-21 11:45:00'),
(17, 5, 'in_transit', 'Albuquerque, NM', 'Package in transit - arrived at Albuquerque hub', 6, NULL, NULL, '2024-01-21 21:20:00'),
(18, 6, 'pending', 'San Jose, CA', 'Package received at origin facility', 7, NULL, NULL, '2024-01-18 06:20:00'),
(19, 6, 'picked_up', 'San Jose, CA', 'Package picked up by courier', 7, NULL, NULL, '2024-01-18 08:00:00'),
(20, 6, 'in_transit', 'Sacramento, CA', 'Package in transit', 7, NULL, NULL, '2024-01-18 13:30:00'),
(21, 6, 'in_transit', 'Denver, CO', 'Package in transit - arrived at Denver hub', 7, NULL, NULL, '2024-01-19 03:15:00'),
(22, 6, 'out_for_delivery', 'Austin, TX', 'Package out for delivery', 7, NULL, NULL, '2024-01-19 05:00:00'),
(23, 6, 'delivered', 'Austin, TX', 'Package delivered successfully', 7, NULL, NULL, '2024-01-19 10:30:00'),
(24, 7, 'pending', 'Seattle, WA', 'Package received at origin facility', 3, NULL, NULL, '2024-01-22 08:45:00'),
(25, 7, 'picked_up', 'Seattle, WA', 'Package picked up by courier', 3, NULL, NULL, '2024-01-22 10:20:00'),
(26, 7, 'in_transit', 'Portland, OR', 'Package in transit', 3, NULL, NULL, '2024-01-22 14:00:00'),
(27, 7, 'in_transit', 'Denver, CO', 'Package in transit - arrived at Denver hub', 3, NULL, NULL, '2024-01-23 07:30:00'),
(28, 8, 'pending', 'Denver, CO', 'Package received at origin facility', 4, NULL, NULL, '2024-01-21 02:30:00'),
(29, 8, 'picked_up', 'Denver, CO', 'Package picked up by courier', 4, NULL, NULL, '2024-01-21 03:00:00'),
(30, 8, 'out_for_delivery', 'Los Angeles, CA', 'Package out for delivery', 4, NULL, NULL, '2024-01-21 06:00:00'),
(31, 8, 'delivered', 'Los Angeles, CA', 'Same-day delivery completed', 4, NULL, NULL, '2024-01-21 09:45:00'),
(38, 3, 'in_transit', 'Admin Panel', 'Status updated to In_transit', 1, NULL, NULL, '2025-07-25 12:32:41'),
(39, 8, 'pending', 'Admin Panel', 'Status updated to Pending', 1, NULL, NULL, '2025-07-25 18:30:42'),
(40, 19, 'pending', 'Admin Panel', 'Status updated to Pending', 1, NULL, NULL, '2025-07-25 18:30:52'),
(41, 22, 'pending', 'New York', 'Package received at origin facility', 9, NULL, NULL, '2025-07-25 20:29:57'),
(46, 4, 'delivered', 'Admin Panel', 'Status updated to Delivered', 1, NULL, NULL, '2025-07-28 15:27:46'),
(47, 26, 'pending', 'Houston', 'Package received at origin facility', 9, NULL, NULL, '2025-07-28 17:06:56'),
(48, 26, 'in_transit', 'Admin Panel', 'Status updated to In_transit', 1, NULL, NULL, '2025-07-28 17:08:43'),
(49, 10, 'cancelled', 'Admin Panel', 'Status updated to Cancelled', 1, NULL, NULL, '2025-07-28 17:14:35'),
(0, 0, 'pending', 'Karachi', 'Package received at origin facility', 9, NULL, NULL, '2025-08-02 05:38:24'),
(0, 0, 'in_transit', 'Admin Panel', 'Status updated to In_transit', 1, NULL, NULL, '2025-08-02 05:40:08');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `customer_code` varchar(20) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `default_address` text DEFAULT NULL,
  `billing_address` text DEFAULT NULL,
  `total_orders` int(11) DEFAULT 0,
  `total_spent` decimal(10,2) DEFAULT 0.00,
  `status` enum('active','inactive') DEFAULT 'active',
  `preferred_delivery_time` varchar(50) DEFAULT 'anytime',
  `package_instructions` text DEFAULT NULL,
  `registered_date` date DEFAULT NULL,
  `last_order_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `user_id`, `customer_code`, `name`, `email`, `phone`, `default_address`, `billing_address`, `total_orders`, `total_spent`, `status`, `preferred_delivery_time`, `package_instructions`, `registered_date`, `last_order_date`, `created_at`, `updated_at`) VALUES
(2, 11, 'CUST002', 'Ali Raza', 'ali.raza@email.pk', '+92 309 012 3456', 'House 123, Block 7, Clifton, Karachi, Sindh', 'House 123, Block 7, Clifton, Karachi, Sindh', 19, '1890.00', 'active', 'afternoon', 'Ring doorbell', '2023-02-20', '2025-07-23', '2025-07-23 11:13:52', '2025-07-30 16:15:33'),
(3, 12, 'CUST003', 'Sadia Khan', 'sadia.khan@email.pk', '+92 310 123 4567', 'Flat 45, Gulberg III, Lahore, Punjab', 'Flat 45, Gulberg III, Lahore, Punjab', 33, '3120.00', 'active', 'evening', 'Call before delivery', '2022-11-10', '2025-07-23', '2025-07-23 11:13:52', '2025-07-30 16:15:33'),
(4, 13, 'CUST004', 'Hassan Ali', 'hassan.ali@email.pk', '+92 311 234 5678', 'House 67, Satellite Town, Rawalpindi, Punjab', 'House 67, Satellite Town, Rawalpindi, Punjab', 8, '670.00', 'active', 'anytime', 'Leave with neighbor', '2023-08-15', '2025-07-23', '2025-07-23 11:13:52', '2025-07-30 16:15:33'),
(5, 14, 'CUST005', 'Amina Malik', 'amina.malik@email.pk', '+92 312 345 6789', 'Shop 12, Ghanta Ghar, Faisalabad, Punjab', 'Shop 12, Ghanta Ghar, Faisalabad, Punjab', 45, '4200.00', 'active', 'morning', 'Signature required', '2022-06-30', '2025-07-26', '2025-07-23 11:13:52', '2025-07-30 16:15:33'),
(6, 15, 'CUST006', 'Omar Khan', 'omar.khan@email.pk', '+92 313 456 7890', 'House 89, Model Town, Multan, Punjab', 'House 89, Model Town, Multan, Punjab', 17, '1450.00', 'active', 'afternoon', 'Leave at back door', '2023-03-22', '2025-07-23', '2025-07-23 11:13:52', '2025-07-30 16:15:33'),
(7, 16, 'CUST007', 'Fatima Hassan', 'fatima.hassan@email.pk', '+92 314 567 8901', 'Flat 23, Saddar, Hyderabad, Sindh', 'Flat 23, Saddar, Hyderabad, Sindh', 30, '2890.00', 'active', 'evening', 'Call on arrival', '2023-01-08', '2025-07-23', '2025-07-23 11:13:52', '2025-07-30 16:15:33'),
(8, 17, 'CUST008', 'Ahmed Raza', 'ahmed.raza@email.pk', '+92 315 678 9012', 'House 34, Satellite Town, Gujranwala, Punjab', 'House 34, Satellite Town, Gujranwala, Punjab', 13, '1200.00', 'active', 'anytime', 'Leave in mailbox', '2023-07-14', '2025-07-23', '2025-07-23 11:13:52', '2025-07-30 16:15:33'),
(9, 18, 'CUST009', 'Sara Ali', 'sara.ali@email.pk', '+92 316 789 0123', 'Shop 56, University Road, Peshawar, KPK', 'Shop 56, University Road, Peshawar, KPK', 37, '3650.00', 'active', 'morning', 'Ring bell twice', '2022-09-03', '2025-07-23', '2025-07-23 11:13:52', '2025-07-30 16:15:33'),
(10, 19, 'CUST010', 'Bilal Khan', 'bilal.khan@email.pk', '+92 317 890 1234', 'House 78, Jinnah Road, Quetta, Balochistan', 'House 78, Jinnah Road, Quetta, Balochistan', 20, '1950.00', 'active', 'afternoon', 'Leave with concierge', '2023-04-17', '2025-07-23', '2025-07-23 11:13:52', '2025-07-30 16:15:33'),
(11, 9, NULL, 'Mike Wilson', '', '', '', NULL, 2, '0.00', 'active', 'anytime', NULL, '2025-07-26', '2025-08-02', '2025-07-25 21:14:27', '2025-08-02 05:38:24');

-- --------------------------------------------------------

--
-- Stand-in structure for view `customer_summary`
-- (See below for the actual view)
--
CREATE TABLE `customer_summary` (
`customer_id` int(11)
,`customer_code` varchar(20)
,`customer_name` varchar(255)
,`customer_email` varchar(255)
,`customer_phone` varchar(20)
,`customer_status` enum('active','inactive')
,`total_orders` int(11)
,`total_spent` decimal(10,2)
,`registered_date` date
,`last_order_date` date
,`actual_total_orders` bigint(21)
,`actual_total_spent` decimal(30,2)
,`delivered_orders` bigint(21)
,`in_transit_orders` bigint(21)
,`pending_orders` bigint(21)
,`cancelled_orders` bigint(21)
,`last_actual_order_date` timestamp
,`avg_order_value` decimal(9,2)
,`days_since_last_order` int(7)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `daily_stats`
-- (See below for the actual view)
--
CREATE TABLE `daily_stats` (
`date` date
,`total_couriers` bigint(21)
,`pending_couriers` bigint(21)
,`in_transit_couriers` bigint(21)
,`delivered_couriers` bigint(21)
,`cancelled_couriers` bigint(21)
,`standard_couriers` bigint(21)
,`express_couriers` bigint(21)
,`overnight_couriers` bigint(21)
,`same_day_couriers` bigint(21)
,`avg_weight` decimal(9,2)
,`avg_delivery_fee` decimal(9,2)
,`total_revenue` decimal(30,2)
,`avg_package_value` decimal(11,2)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `monthly_revenue`
-- (See below for the actual view)
--
CREATE TABLE `monthly_revenue` (
`year` int(4)
,`month` int(2)
,`month_name` varchar(9)
,`total_shipments` bigint(21)
,`delivered_shipments` bigint(21)
,`total_revenue` decimal(30,2)
,`delivered_revenue` decimal(30,2)
,`avg_shipment_value` decimal(9,2)
,`active_agents` bigint(21)
,`active_customers` bigint(21)
);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `courier_id` int(11) DEFAULT NULL,
  `type` enum('courier_update','delivery_alert','system_notification','promotional') NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `courier_id`, `type`, `title`, `message`, `is_read`, `created_at`, `read_at`) VALUES
(1, 9, 1, 'courier_update', 'Package Update', 'Your package CMS001234 is now in transit', 0, '2024-01-21 03:20:00', NULL),
(2, 9, 7, 'courier_update', 'Package Picked Up', 'Your package CMS001240 has been picked up', 0, '2024-01-22 10:20:00', NULL),
(4, 11, 2, 'courier_update', 'Package Delivered', 'Your package CMS001235 has been delivered successfully', 1, '2024-01-21 09:20:00', NULL),
(5, 12, 2, 'delivery_alert', 'Delivery Confirmation', 'Package CMS001235 delivered to Carol Brown', 1, '2024-01-21 09:25:00', NULL),
(6, 14, 3, 'courier_update', 'Package Pending', 'Your package CMS001236 is awaiting pickup', 0, '2024-01-21 04:15:00', NULL),
(7, 15, 19, 'courier_update', 'Package In Transit', 'Your package CMS001252 is now in transit', 0, '2024-01-25 04:30:00', NULL),
(8, 1, NULL, 'system_notification', 'System Maintenance', 'Scheduled maintenance tonight from 2-4 AM EST', 0, '2024-01-24 11:00:00', NULL),
(9, 3, NULL, 'system_notification', 'New Feature Available', 'Real-time tracking is now available for all shipments', 1, '2024-01-20 04:00:00', NULL),
(10, 4, NULL, 'system_notification', 'Performance Report', 'Your monthly performance report is ready', 0, '2024-01-25 03:00:00', NULL),
(11, 3, 1, 'courier_update', 'New Assignment', 'You have been assigned courier CMS001234', 1, '2024-01-20 05:30:00', NULL),
(12, 3, 3, 'courier_update', 'Pickup Required', 'Package CMS001236 requires pickup', 0, '2024-01-21 04:45:00', NULL),
(13, 4, 5, 'courier_update', 'Priority Delivery', 'Urgent: Package CMS001238 requires immediate attention', 1, '2024-01-21 03:30:00', NULL),
(14, 6, 13, 'courier_update', 'New Assignment', 'You have been assigned courier CMS001246', 0, '2024-01-22 06:30:00', NULL);

-- --------------------------------------------------------

--
-- Stand-in structure for view `overdue_shipments`
-- (See below for the actual view)
--
CREATE TABLE `overdue_shipments` (
`id` int(11)
,`tracking_number` varchar(50)
,`sender_name` varchar(255)
,`receiver_name` varchar(255)
,`pickup_city` varchar(100)
,`delivery_city` varchar(100)
,`courier_type` enum('standard','express','overnight','same-day')
,`status` enum('pending','in_transit','delivered','cancelled')
,`priority` enum('low','medium','high','urgent')
,`delivery_date` date
,`days_overdue` int(7)
,`agent_code` varchar(20)
,`agent_name` varchar(255)
,`agent_email` varchar(255)
,`created_at` timestamp
);

-- --------------------------------------------------------

--
-- Table structure for table `reports`
--

CREATE TABLE `reports` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` enum('daily','weekly','monthly','custom') NOT NULL,
  `generated_by` int(11) NOT NULL,
  `date_from` date DEFAULT NULL,
  `date_to` date DEFAULT NULL,
  `parameters` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`parameters`)),
  `file_path` varchar(500) DEFAULT NULL,
  `status` enum('generating','completed','failed') DEFAULT 'generating',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reports`
--

INSERT INTO `reports` (`id`, `name`, `type`, `generated_by`, `date_from`, `date_to`, `parameters`, `file_path`, `status`, `created_at`, `completed_at`) VALUES
(1, 'Daily Courier Report - Jan 24', 'daily', 1, '2024-01-24', '2024-01-24', '{\"include_cancelled\": false, \"group_by_agent\": true}', NULL, 'completed', '2024-01-24 13:00:00', '2024-01-24 13:05:00'),
(2, 'Weekly Performance Report', 'weekly', 1, '2024-01-15', '2024-01-21', '{\"include_metrics\": true, \"agent_performance\": true}', NULL, 'completed', '2024-01-22 04:00:00', '2024-01-22 04:12:00'),
(3, 'Monthly Summary - December', 'monthly', 1, '2023-12-01', '2023-12-31', '{\"financial_summary\": true, \"top_routes\": true}', NULL, 'completed', '2024-01-01 05:00:00', '2024-01-01 05:25:00'),
(4, 'Agent Performance Report', 'custom', 3, '2024-01-01', '2024-01-25', '{\"agent_id\": 1, \"detailed_tracking\": true}', NULL, 'generating', '2024-01-25 09:30:00', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `key_name` varchar(100) NOT NULL,
  `value` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `type` enum('string','number','boolean','json') DEFAULT 'string',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `key_name`, `value`, `description`, `type`, `created_at`, `updated_at`) VALUES
(1, 'site_name', 'CourierPro Management System', 'Name of the courier management system', 'string', '2025-07-23 11:13:52', '2025-07-23 11:13:52'),
(2, 'site_email', 'admin@courierpro.pk', 'Main contact email for the system', 'string', '2025-07-23 11:13:52', '2025-07-30 16:15:33'),
(3, 'site_phone', '+92 21 123 4567', 'Main contact phone number for Pakistan', 'string', '2025-07-23 11:13:52', '2025-07-30 16:15:33'),
(4, 'default_currency', 'PKR', 'Default currency for pricing (Pakistani Rupee)', 'string', '2025-07-23 11:13:52', '2025-07-30 16:15:33'),
(5, 'default_timezone', 'Asia/Karachi', 'Default timezone for Pakistan', 'string', '2025-07-23 11:13:52', '2025-07-30 16:15:33'),
(6, 'max_package_weight', '50', 'Maximum package weight in kg', 'number', '2025-07-23 11:13:52', '2025-07-23 11:13:52'),
(7, 'delivery_fee_standard', '1500.00', 'Standard delivery fee in PKR', 'number', '2025-07-23 11:13:52', '2025-07-30 16:15:33'),
(8, 'delivery_fee_express', '2500.00', 'Express delivery fee in PKR', 'number', '2025-07-23 11:13:52', '2025-07-30 16:15:33'),
(9, 'delivery_fee_overnight', '4500.00', 'Overnight delivery fee in PKR', 'number', '2025-07-23 11:13:52', '2025-07-30 16:15:33'),
(10, 'delivery_fee_same_day', '6500.00', 'Same day delivery fee in PKR', 'number', '2025-07-23 11:13:52', '2025-07-30 16:15:33'),
(11, 'email_notifications_enabled', 'true', 'Enable email notifications', 'boolean', '2025-07-23 11:13:52', '2025-07-23 11:13:52'),
(12, 'sms_notifications_enabled', 'true', 'Enable SMS notifications', 'boolean', '2025-07-23 11:13:52', '2025-07-23 11:13:52'),
(13, 'auto_assign_agents', 'false', 'Automatically assign agents to new couriers', 'boolean', '2025-07-23 11:13:52', '2025-07-23 11:13:52'),
(14, 'tracking_update_interval', '30', 'Tracking update interval in minutes', 'number', '2025-07-23 11:13:52', '2025-07-23 11:13:52'),
(15, 'max_daily_orders_per_agent', '25', 'Maximum daily orders per agent', 'number', '2025-07-23 11:13:52', '2025-07-23 11:13:52'),
(16, 'business_hours_start', '09:00', 'Business hours start time', 'string', '2025-07-23 11:13:52', '2025-07-23 11:13:52'),
(17, 'business_hours_end', '17:00', 'Business hours end time', 'string', '2025-07-23 11:13:52', '2025-07-23 11:13:52'),
(18, 'supported_cities', '[\"Karachi\", \"Lahore\", \"Faisalabad\", \"Rawalpindi\", \"Multan\", \"Hyderabad\", \"Gujranwala\", \"Peshawar\", \"Quetta\", \"Islamabad\"]', 'List of supported cities in Pakistan', 'json', '2025-07-23 11:13:52', '2025-07-30 16:15:33');

-- --------------------------------------------------------

--
-- Table structure for table `support_messages`
--

CREATE TABLE `support_messages` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `status` enum('open','closed','in_progress') DEFAULT 'open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `support_messages`
--

INSERT INTO `support_messages` (`id`, `name`, `email`, `category`, `subject`, `message`, `status`, `created_at`) VALUES
(1, 'OPrizvi', 'admin@courierpro.com', 'Delivery Issues', 'test', 'test for the message', 'open', '2025-07-23 22:09:38'),
(2, 'OPrizvi', 'admin@courierpro.com', 'Delivery Issues', 'test', 'test for the message', 'open', '2025-07-23 22:13:51');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('admin','agent','user') NOT NULL DEFAULT 'user',
  `status` enum('active','inactive') DEFAULT 'active',
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `phone`, `role`, `status`, `email_verified_at`, `remember_token`, `created_at`, `updated_at`) VALUES
(1, 'Ahmed Khan', 'admin@courierpro.pk', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+92 300 123 4567', 'admin', 'active', NULL, NULL, '2023-01-01 05:00:00', '2025-07-30 16:15:33'),
(2, 'Fatima Ali', 'admin2@courierpro.pk', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+92 301 234 5678', 'admin', 'active', NULL, NULL, '2023-01-01 05:00:00', '2025-07-30 16:15:33'),
(3, 'Muhammad Hassan', 'agent@courierpro.pk', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+92 302 345 6789', 'agent', 'active', NULL, NULL, '2023-01-15 04:00:00', '2025-07-30 16:15:33'),
(4, 'Ayesha Malik', 'ayesha.malik@courierpro.pk', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+92 303 456 7890', 'agent', 'active', NULL, NULL, '2023-02-20 04:00:00', '2025-07-30 16:15:33'),
(5, 'Bilal Ahmed', 'bilal.ahmed@courierpro.pk', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+92 304 567 8901', 'agent', 'inactive', NULL, NULL, '2023-03-10 04:00:00', '2025-07-30 16:15:33'),
(6, 'Sana Khan', 'sana.khan@courierpro.pk', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+92 305 678 9012', 'agent', 'active', NULL, NULL, '2022-11-05 04:00:00', '2025-07-30 16:15:33'),
(7, 'Usman Ali', 'usman.ali@courierpro.pk', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+92 306 789 0123', 'agent', 'inactive', NULL, NULL, '2023-04-12 04:00:00', '2025-07-30 16:15:33'),
(8, 'Zara Hassan', 'zara.hassan@courierpro.pk', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+92 307 890 1234', 'agent', 'active', NULL, NULL, '2023-05-18 04:00:00', '2025-07-30 16:15:33'),
(9, 'Imran Khan', 'user@courierpro.pk', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+92 308 901 2345', 'user', 'active', NULL, NULL, '2023-06-01 05:00:00', '2025-07-30 16:15:33'),
(11, 'Bob Johnson', 'bob.johnson@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+1 555 0102', 'user', 'active', NULL, NULL, '2023-02-20 05:00:00', '2025-07-23 11:13:52'),
(12, 'Carol Brown', 'carol.brown@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+1 555 0103', 'user', 'active', NULL, NULL, '2023-03-10 05:00:00', '2025-07-23 11:13:52'),
(13, 'David Wilson', 'david.wilson@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+1 555 0104', 'user', 'active', NULL, NULL, '2023-04-05 05:00:00', '2025-07-23 11:13:52'),
(14, 'Eva Davis', 'eva.davis@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+1 555 0105', 'user', 'active', NULL, NULL, '2023-05-12 05:00:00', '2025-07-23 11:13:52'),
(15, 'Frank Miller', 'frank.miller@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+1 555 0106', 'user', 'active', NULL, NULL, '2023-06-18 05:00:00', '2025-07-23 11:13:52'),
(16, 'Grace Lee', 'grace.lee@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+1 555 0107', 'user', 'active', NULL, NULL, '2023-07-22 05:00:00', '2025-07-23 11:13:52'),
(17, 'Henry Taylor', 'henry.taylor@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+1 555 0108', 'user', 'active', NULL, NULL, '2023-08-15 05:00:00', '2025-07-23 11:13:52'),
(18, 'Ivy Chen', 'ivy.chen@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+1 555 0109', 'user', 'active', NULL, NULL, '2023-09-10 05:00:00', '2025-07-23 11:13:52'),
(19, 'Jack Robinson', 'jack.robinson@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+1 555 0110', 'user', 'active', NULL, NULL, '2023-10-05 05:00:00', '2025-07-23 11:13:52'),
(20, 'Kate Anderson', 'kate.anderson@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+1 555 0111', 'user', 'active', NULL, NULL, '2023-11-12 05:00:00', '2025-07-23 11:13:52'),
(21, 'Liam Murphy', 'liam.murphy@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+1 555 0112', 'user', 'active', NULL, NULL, '2023-12-01 05:00:00', '2025-07-23 11:13:52');

-- --------------------------------------------------------

--
-- Table structure for table `user_preferences`
--

CREATE TABLE `user_preferences` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `email_notifications` tinyint(1) DEFAULT 1,
  `sms_notifications` tinyint(1) DEFAULT 0,
  `push_notifications` tinyint(1) DEFAULT 1,
  `order_updates` tinyint(1) DEFAULT 1,
  `system_alerts` tinyint(1) DEFAULT 1,
  `marketing_emails` tinyint(1) DEFAULT 0,
  `delivery_alerts` tinyint(1) DEFAULT 1,
  `theme` varchar(20) DEFAULT 'light',
  `language` varchar(10) DEFAULT 'en',
  `timezone` varchar(50) DEFAULT 'America/New_York',
  `currency` varchar(10) DEFAULT 'USD',
  `date_format` varchar(20) DEFAULT 'MM/DD/YYYY',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_preferences`
--

INSERT INTO `user_preferences` (`id`, `user_id`, `email_notifications`, `sms_notifications`, `push_notifications`, `order_updates`, `system_alerts`, `marketing_emails`, `delivery_alerts`, `theme`, `language`, `timezone`, `currency`, `date_format`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 0, 1, 1, 0, 0, 1, 'Light', 'en', 'Asia/Karachi', 'PKR', 'MM/DD/YYYY', '2025-07-23 11:13:52', '2025-07-30 16:15:34'),
(2, 3, 1, 1, 1, 1, 1, 0, 1, 'light', 'en', 'Asia/Karachi', 'PKR', 'MM/DD/YYYY', '2025-07-23 11:13:52', '2025-07-30 16:15:34'),
(3, 4, 1, 0, 1, 1, 1, 0, 1, 'dark', 'en', 'Asia/Karachi', 'PKR', 'MM/DD/YYYY', '2025-07-23 11:13:52', '2025-07-30 16:15:34'),
(4, 9, 1, 1, 0, 1, 0, 1, 1, 'light', 'en', 'Asia/Karachi', 'PKR', 'MM/DD/YYYY', '2025-07-23 11:13:52', '2025-07-30 16:15:34'),
(6, 11, 1, 0, 1, 1, 1, 1, 1, 'dark', 'en', 'America/Chicago', 'USD', 'DD/MM/YYYY', '2025-07-23 11:13:52', '2025-07-23 11:13:52'),
(7, 12, 1, 1, 1, 1, 1, 0, 1, 'light', 'en', 'America/Chicago', 'USD', 'MM/DD/YYYY', '2025-07-23 11:13:52', '2025-07-23 11:13:52');

-- --------------------------------------------------------

--
-- Structure for view `agent_performance`
--
DROP TABLE IF EXISTS `agent_performance`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `agent_performance`  AS SELECT `a`.`id` AS `agent_id`, `a`.`agent_code` AS `agent_code`, `u`.`name` AS `agent_name`, `u`.`email` AS `agent_email`, `c`.`name` AS `city_name`, `a`.`total_couriers` AS `total_couriers`, `a`.`success_rate` AS `success_rate`, `a`.`rating` AS `rating`, `a`.`availability` AS `availability`, `a`.`max_daily_orders` AS `max_daily_orders`, count(`co`.`id`) AS `total_assigned_couriers`, count(case when `co`.`status` = 'delivered' then 1 end) AS `delivered_couriers`, count(case when `co`.`status` = 'in_transit' then 1 end) AS `in_transit_couriers`, count(case when `co`.`status` = 'pending' then 1 end) AS `pending_couriers`, count(case when `co`.`status` = 'cancelled' then 1 end) AS `cancelled_couriers`, round(count(case when `co`.`status` = 'delivered' then 1 end) * 100.0 / nullif(count(case when `co`.`status` <> 'pending' then 1 end),0),2) AS `actual_success_rate`, avg(`co`.`delivery_fee`) AS `avg_delivery_fee`, sum(`co`.`delivery_fee`) AS `total_revenue`, count(case when `co`.`status` = 'delivered' and `co`.`actual_delivery_date` <= `co`.`delivery_date` then 1 end) AS `on_time_deliveries`, round(count(case when `co`.`status` = 'delivered' and `co`.`actual_delivery_date` <= `co`.`delivery_date` then 1 end) * 100.0 / nullif(count(case when `co`.`status` = 'delivered' then 1 end),0),2) AS `on_time_percentage` FROM (((`agents` `a` join `users` `u` on(`a`.`user_id` = `u`.`id`)) left join `cities` `c` on(`a`.`city_id` = `c`.`id`)) left join `couriers` `co` on(`a`.`id` = `co`.`assigned_agent_id`)) GROUP BY `a`.`id`, `a`.`agent_code`, `u`.`name`, `u`.`email`, `c`.`name`, `a`.`total_couriers`, `a`.`success_rate`, `a`.`rating`, `a`.`availability`, `a`.`max_daily_orders``max_daily_orders`  ;

-- --------------------------------------------------------

--
-- Structure for view `courier_details`
--
DROP TABLE IF EXISTS `courier_details`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `courier_details`  AS SELECT `c`.`id` AS `id`, `c`.`tracking_number` AS `tracking_number`, `c`.`sender_name` AS `sender_name`, `c`.`sender_phone` AS `sender_phone`, `c`.`receiver_name` AS `receiver_name`, `c`.`receiver_phone` AS `receiver_phone`, `c`.`pickup_city` AS `pickup_city`, `c`.`delivery_city` AS `delivery_city`, `pc`.`name` AS `pickup_city_full`, `dc`.`name` AS `delivery_city_full`, `c`.`courier_type` AS `courier_type`, `c`.`weight` AS `weight`, `c`.`package_value` AS `package_value`, `c`.`delivery_fee` AS `delivery_fee`, `c`.`status` AS `status`, `c`.`priority` AS `priority`, `c`.`delivery_date` AS `delivery_date`, `c`.`actual_delivery_date` AS `actual_delivery_date`, `c`.`pickup_date` AS `pickup_date`, `c`.`special_instructions` AS `special_instructions`, `c`.`notes` AS `notes`, `u`.`name` AS `created_by_name`, `u`.`email` AS `created_by_email`, `a`.`agent_code` AS `agent_code`, `au`.`name` AS `agent_name`, `au`.`email` AS `agent_email`, `cust`.`name` AS `customer_name`, `cust`.`email` AS `customer_email`, `c`.`created_at` AS `created_at`, `c`.`updated_at` AS `updated_at`, CASE WHEN `c`.`status` = 'delivered' AND `c`.`actual_delivery_date` <= `c`.`delivery_date` THEN 'On Time' WHEN `c`.`status` = 'delivered' AND `c`.`actual_delivery_date` > `c`.`delivery_date` THEN 'Late' WHEN `c`.`status` <> 'delivered' AND curdate() > `c`.`delivery_date` THEN 'Overdue' ELSE 'On Schedule' END AS `delivery_status` FROM ((((((`couriers` `c` left join `cities` `pc` on(`c`.`pickup_city_id` = `pc`.`id`)) left join `cities` `dc` on(`c`.`delivery_city_id` = `dc`.`id`)) left join `users` `u` on(`c`.`created_by` = `u`.`id`)) left join `agents` `a` on(`c`.`assigned_agent_id` = `a`.`id`)) left join `users` `au` on(`a`.`user_id` = `au`.`id`)) left join `customers` `cust` on(`c`.`customer_id` = `cust`.`id`))  ;

-- --------------------------------------------------------

--
-- Structure for view `customer_summary`
--
DROP TABLE IF EXISTS `customer_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `customer_summary`  AS SELECT `c`.`id` AS `customer_id`, `c`.`customer_code` AS `customer_code`, `c`.`name` AS `customer_name`, `c`.`email` AS `customer_email`, `c`.`phone` AS `customer_phone`, `c`.`status` AS `customer_status`, `c`.`total_orders` AS `total_orders`, `c`.`total_spent` AS `total_spent`, `c`.`registered_date` AS `registered_date`, `c`.`last_order_date` AS `last_order_date`, count(`co`.`id`) AS `actual_total_orders`, sum(`co`.`delivery_fee`) AS `actual_total_spent`, count(case when `co`.`status` = 'delivered' then 1 end) AS `delivered_orders`, count(case when `co`.`status` = 'in_transit' then 1 end) AS `in_transit_orders`, count(case when `co`.`status` = 'pending' then 1 end) AS `pending_orders`, count(case when `co`.`status` = 'cancelled' then 1 end) AS `cancelled_orders`, max(`co`.`created_at`) AS `last_actual_order_date`, round(avg(`co`.`delivery_fee`),2) AS `avg_order_value`, to_days(curdate()) - to_days(`c`.`last_order_date`) AS `days_since_last_order` FROM (`customers` `c` left join `couriers` `co` on(`c`.`id` = `co`.`customer_id`)) GROUP BY `c`.`id`, `c`.`customer_code`, `c`.`name`, `c`.`email`, `c`.`phone`, `c`.`status`, `c`.`total_orders`, `c`.`total_spent`, `c`.`registered_date`, `c`.`last_order_date``last_order_date`  ;

-- --------------------------------------------------------

--
-- Structure for view `daily_stats`
--
DROP TABLE IF EXISTS `daily_stats`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `daily_stats`  AS SELECT cast(`couriers`.`created_at` as date) AS `date`, count(0) AS `total_couriers`, count(case when `couriers`.`status` = 'pending' then 1 end) AS `pending_couriers`, count(case when `couriers`.`status` = 'in_transit' then 1 end) AS `in_transit_couriers`, count(case when `couriers`.`status` = 'delivered' then 1 end) AS `delivered_couriers`, count(case when `couriers`.`status` = 'cancelled' then 1 end) AS `cancelled_couriers`, count(case when `couriers`.`courier_type` = 'standard' then 1 end) AS `standard_couriers`, count(case when `couriers`.`courier_type` = 'express' then 1 end) AS `express_couriers`, count(case when `couriers`.`courier_type` = 'overnight' then 1 end) AS `overnight_couriers`, count(case when `couriers`.`courier_type` = 'same-day' then 1 end) AS `same_day_couriers`, round(avg(`couriers`.`weight`),2) AS `avg_weight`, round(avg(`couriers`.`delivery_fee`),2) AS `avg_delivery_fee`, round(sum(`couriers`.`delivery_fee`),2) AS `total_revenue`, round(avg(`couriers`.`package_value`),2) AS `avg_package_value` FROM `couriers` GROUP BY cast(`couriers`.`created_at` as date) ORDER BY cast(`couriers`.`created_at` as date) AS `DESCdesc` ASC  ;

-- --------------------------------------------------------

--
-- Structure for view `monthly_revenue`
--
DROP TABLE IF EXISTS `monthly_revenue`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `monthly_revenue`  AS SELECT year(`couriers`.`created_at`) AS `year`, month(`couriers`.`created_at`) AS `month`, monthname(`couriers`.`created_at`) AS `month_name`, count(0) AS `total_shipments`, count(case when `couriers`.`status` = 'delivered' then 1 end) AS `delivered_shipments`, round(sum(`couriers`.`delivery_fee`),2) AS `total_revenue`, round(sum(case when `couriers`.`status` = 'delivered' then `couriers`.`delivery_fee` else 0 end),2) AS `delivered_revenue`, round(avg(`couriers`.`delivery_fee`),2) AS `avg_shipment_value`, count(distinct `couriers`.`assigned_agent_id`) AS `active_agents`, count(distinct `couriers`.`customer_id`) AS `active_customers` FROM `couriers` GROUP BY year(`couriers`.`created_at`), month(`couriers`.`created_at`) ORDER BY year(`couriers`.`created_at`) DESC, month(`couriers`.`created_at`) AS `DESCdesc` ASC  ;

-- --------------------------------------------------------

--
-- Structure for view `overdue_shipments`
--
DROP TABLE IF EXISTS `overdue_shipments`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `overdue_shipments`  AS SELECT `c`.`id` AS `id`, `c`.`tracking_number` AS `tracking_number`, `c`.`sender_name` AS `sender_name`, `c`.`receiver_name` AS `receiver_name`, `c`.`pickup_city` AS `pickup_city`, `c`.`delivery_city` AS `delivery_city`, `c`.`courier_type` AS `courier_type`, `c`.`status` AS `status`, `c`.`priority` AS `priority`, `c`.`delivery_date` AS `delivery_date`, to_days(curdate()) - to_days(`c`.`delivery_date`) AS `days_overdue`, `a`.`agent_code` AS `agent_code`, `u`.`name` AS `agent_name`, `u`.`email` AS `agent_email`, `c`.`created_at` AS `created_at` FROM ((`couriers` `c` left join `agents` `a` on(`c`.`assigned_agent_id` = `a`.`id`)) left join `users` `u` on(`a`.`user_id` = `u`.`id`)) WHERE `c`.`status` in ('pending','in_transit') AND `c`.`delivery_date` < curdate() ORDER BY to_days(curdate()) - to_days(`c`.`delivery_date`) DESC, `c`.`priority` AS `DESCdesc` ASC  ;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
