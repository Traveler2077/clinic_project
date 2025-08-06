-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- 主機： 127.0.0.1
-- 產生時間： 2025-08-06 18:36:33
-- 伺服器版本： 10.4.32-MariaDB
-- PHP 版本： 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- 資料庫： `clinic_db`
--

-- --------------------------------------------------------

--
-- 資料表結構 `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `pet_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('member','admin') NOT NULL DEFAULT 'member',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `phone` varchar(20) NOT NULL,
  `address` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 傾印資料表的資料 `users`
--

INSERT INTO `users` (`id`, `name`, `pet_name`, `email`, `password`, `role`, `created_at`, `phone`, `address`) VALUES
(1, 'Men', '', '5566777@3q56', '$2y$10$FtH0IEjeOo3MvuHo3CUWce5OzaLj0EJOfx5HSkzQlRk3b8DTrxeVS', 'member', '2025-08-05 09:15:25', '', NULL),
(5, 'women', '', '5566777@3q566', '$2y$10$hNessbAEvTVFjZIFLzFfcuCSWqFRaFAEvj/mOgCFIWZCdPE74XfFG', 'member', '2025-08-05 09:59:42', '', NULL),
(9, 'women2', '', '7876710@gmail.com', '$2y$10$A94Pam.FZYfQ.bYQc6l0e.kNu0hrj8OFf01ulbngyeDA2IsyFMh8y', 'member', '2025-08-05 10:05:08', '', NULL),
(13, 'women', '', '5566777@3q569', '$2y$10$dwppwEcc0UuBHjUAd.MsMubk3fsMya02WhcMxvss0s6iWuKS9mmx.', 'member', '2025-08-05 10:12:33', '', NULL),
(20, 'men', '', '123456@123456', '$2y$10$YE8eLrTPvzS3PitXKKtbJeKeDyZ9GkDrcnIoTtYoTs5YbrKTEm04C', 'member', '2025-08-06 08:35:16', '', NULL),
(21, '123', '', '123@123', '$2y$10$8Ea3SDn47sipK4d6GSeEHOFAOF8vTeV3JkSa3q2UfAPUwtHKEBoPm', '', '2025-08-06 14:30:04', '123456789', ''),
(22, '456', '', '456@456', '$2y$10$oieDaFcvZgDkCit/5JZra.gAV8IhCo8vZf41Q95e6JiTWl5uhyDQ6', '', '2025-08-06 14:30:50', '123456789', ''),
(23, '789', '', '789@789', '$2y$10$Ms/wYCFFP/QStkmpER5Aa.H31dDBklDNF9xPk7EKzg1XgvawpDgme', '', '2025-08-06 14:49:42', '123456789', '3654'),
(24, '741', '', '741@741', '$2y$10$6m16mhbT/BGJAoC0xVJEC.PVQTblGTYBr6yIQT2aCW2MJww0U5GKy', 'member', '2025-08-06 14:51:37', '123456789', '456789');

--
-- 已傾印資料表的索引
--

--
-- 資料表索引 `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- 在傾印的資料表使用自動遞增(AUTO_INCREMENT)
--

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
