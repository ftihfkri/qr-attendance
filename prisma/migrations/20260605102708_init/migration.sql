-- CreateTable
CREATE TABLE `shareholders` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(191) NOT NULL,
    `koperasi_id` VARCHAR(191) NOT NULL,
    `phone_number` VARCHAR(191) NOT NULL,
    `email` VARCHAR(191) NULL,
    `address` TEXT NULL,
    `registered_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),

    UNIQUE INDEX `shareholders_koperasi_id_key`(`koperasi_id`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `meetings` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(191) NOT NULL,
    `meeting_date` DATETIME(3) NOT NULL,
    `venue_name` VARCHAR(191) NULL,
    `venue_lat` DOUBLE NULL,
    `venue_lng` DOUBLE NULL,
    `radius_meters` INTEGER NOT NULL DEFAULT 100,
    `is_active` BOOLEAN NOT NULL DEFAULT false,
    `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),

    INDEX `meetings_is_active_idx`(`is_active`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `attendance` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `meeting_id` INTEGER NOT NULL,
    `shareholder_id` INTEGER NOT NULL,
    `koperasi_id` VARCHAR(191) NOT NULL,
    `name` VARCHAR(191) NOT NULL,
    `phone_number` VARCHAR(191) NOT NULL,
    `date` VARCHAR(191) NOT NULL,
    `time` VARCHAR(191) NOT NULL,
    `location_lat` DOUBLE NOT NULL,
    `location_lng` DOUBLE NOT NULL,
    `device_fingerprint` VARCHAR(191) NOT NULL,
    `status` VARCHAR(191) NOT NULL DEFAULT 'present',
    `distance_from_venue` DOUBLE NULL,
    `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `updated_at` DATETIME(3) NOT NULL,

    INDEX `attendance_koperasi_id_date_idx`(`koperasi_id`, `date`),
    UNIQUE INDEX `attendance_meeting_id_koperasi_id_key`(`meeting_id`, `koperasi_id`),
    UNIQUE INDEX `attendance_meeting_id_device_fingerprint_key`(`meeting_id`, `device_fingerprint`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `admin_sessions` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `admin_id` INTEGER NULL,
    `token` VARCHAR(191) NOT NULL,
    `ip_address` VARCHAR(191) NULL,
    `user_agent` VARCHAR(191) NULL,
    `expires_at` DATETIME(3) NOT NULL,
    `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `updated_at` DATETIME(3) NOT NULL,

    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `qr_logs` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `session_id` VARCHAR(191) NOT NULL,
    `generated_by` VARCHAR(191) NOT NULL,
    `expires_at` DATETIME(3) NOT NULL,
    `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),

    UNIQUE INDEX `qr_logs_session_id_key`(`session_id`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `settings` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `key` VARCHAR(191) NOT NULL,
    `value` JSON NOT NULL,

    UNIQUE INDEX `settings_key_key`(`key`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `friendships` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `user1_id` INTEGER NOT NULL,
    `user2_id` INTEGER NOT NULL,
    `status` VARCHAR(191) NOT NULL DEFAULT 'accepted',
    `established_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),

    UNIQUE INDEX `friendships_user1_id_user2_id_key`(`user1_id`, `user2_id`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `iot_devices` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(191) NOT NULL,
    `type` VARCHAR(191) NOT NULL DEFAULT 'sensor',
    `location_lat` DOUBLE NULL,
    `location_lng` DOUBLE NULL,
    `building` VARCHAR(191) NULL,
    `room` VARCHAR(191) NULL,
    `status` VARCHAR(191) NOT NULL DEFAULT 'active',
    `installation_date` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `last_communication` DATETIME(3) NULL,

    UNIQUE INDEX `iot_devices_name_key`(`name`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `iot_connections` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `from_device_id` INTEGER NOT NULL,
    `to_device_id` INTEGER NOT NULL,
    `cost` DOUBLE NOT NULL,
    `connection_type` VARCHAR(191) NOT NULL DEFAULT 'wireless',
    `status` VARCHAR(191) NOT NULL DEFAULT 'active',
    `established_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),

    UNIQUE INDEX `iot_connections_from_device_id_to_device_id_key`(`from_device_id`, `to_device_id`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `learning_activities` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(191) NOT NULL,
    `description` TEXT NOT NULL DEFAULT '',
    `value` DOUBLE NOT NULL,
    `weight` DOUBLE NOT NULL,
    `category` VARCHAR(191) NOT NULL DEFAULT 'other',
    `duration_unit` VARCHAR(191) NOT NULL DEFAULT 'hours',
    `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),

    UNIQUE INDEX `learning_activities_name_key`(`name`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `campus_locations` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(191) NOT NULL,
    `description` TEXT NOT NULL DEFAULT '',
    `lat` DOUBLE NOT NULL,
    `lng` DOUBLE NOT NULL,
    `type` VARCHAR(191) NOT NULL DEFAULT 'other',
    `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `updated_at` DATETIME(3) NOT NULL,

    UNIQUE INDEX `campus_locations_name_key`(`name`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `campus_paths` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `from_location_id` INTEGER NOT NULL,
    `to_location_id` INTEGER NOT NULL,
    `distance` DOUBLE NOT NULL,
    `type` VARCHAR(191) NOT NULL DEFAULT 'walking_path',
    `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `updated_at` DATETIME(3) NOT NULL,

    UNIQUE INDEX `campus_paths_from_location_id_to_location_id_key`(`from_location_id`, `to_location_id`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- AddForeignKey
ALTER TABLE `attendance` ADD CONSTRAINT `attendance_meeting_id_fkey` FOREIGN KEY (`meeting_id`) REFERENCES `meetings`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `attendance` ADD CONSTRAINT `attendance_shareholder_id_fkey` FOREIGN KEY (`shareholder_id`) REFERENCES `shareholders`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `iot_connections` ADD CONSTRAINT `iot_connections_from_device_id_fkey` FOREIGN KEY (`from_device_id`) REFERENCES `iot_devices`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `iot_connections` ADD CONSTRAINT `iot_connections_to_device_id_fkey` FOREIGN KEY (`to_device_id`) REFERENCES `iot_devices`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `campus_paths` ADD CONSTRAINT `campus_paths_from_location_id_fkey` FOREIGN KEY (`from_location_id`) REFERENCES `campus_locations`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `campus_paths` ADD CONSTRAINT `campus_paths_to_location_id_fkey` FOREIGN KEY (`to_location_id`) REFERENCES `campus_locations`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE;
