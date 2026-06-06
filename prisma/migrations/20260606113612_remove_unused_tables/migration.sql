/*
  Warnings:

  - You are about to drop the `campus_locations` table. If the table is not empty, all the data it contains will be lost.
  - You are about to drop the `campus_paths` table. If the table is not empty, all the data it contains will be lost.
  - You are about to drop the `friendships` table. If the table is not empty, all the data it contains will be lost.
  - You are about to drop the `iot_connections` table. If the table is not empty, all the data it contains will be lost.
  - You are about to drop the `iot_devices` table. If the table is not empty, all the data it contains will be lost.
  - You are about to drop the `learning_activities` table. If the table is not empty, all the data it contains will be lost.
  - You are about to drop the `qr_logs` table. If the table is not empty, all the data it contains will be lost.
  - You are about to drop the `settings` table. If the table is not empty, all the data it contains will be lost.

*/
-- DropForeignKey
ALTER TABLE `campus_paths` DROP FOREIGN KEY `campus_paths_from_location_id_fkey`;

-- DropForeignKey
ALTER TABLE `campus_paths` DROP FOREIGN KEY `campus_paths_to_location_id_fkey`;

-- DropForeignKey
ALTER TABLE `iot_connections` DROP FOREIGN KEY `iot_connections_from_device_id_fkey`;

-- DropForeignKey
ALTER TABLE `iot_connections` DROP FOREIGN KEY `iot_connections_to_device_id_fkey`;

-- DropTable
DROP TABLE `campus_locations`;

-- DropTable
DROP TABLE `campus_paths`;

-- DropTable
DROP TABLE `friendships`;

-- DropTable
DROP TABLE `iot_connections`;

-- DropTable
DROP TABLE `iot_devices`;

-- DropTable
DROP TABLE `learning_activities`;

-- DropTable
DROP TABLE `qr_logs`;

-- DropTable
DROP TABLE `settings`;
