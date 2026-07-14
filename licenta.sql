CREATE DATABASE licenta
    DEFAULT CHARACTER SET = 'utf8mb4';

USE licenta;

CREATE Table airlines(
    id int auto_increment primary key,
    name varchar(255) not null,
    iata_code varchar(10) not null,
    icao_code varchar(10) not null,
    country varchar(255) not null,

    UNIQUE KEY uq_airlines_iata (iata_code),
    UNIQUE KEY uq_airlines_icao (icao_code)
);

CREATE Table airports(
    id int auto_increment primary key,
    name varchar(255) not null,
    iata_code varchar(10) not null,
    icao_code varchar(10) not null,
    city varchar(255) not null,
    country varchar(255) not null,
    lat double not null,
    lon double not null,

    UNIQUE KEY uq_airports_iata (iata_code),
    UNIQUE KEY uq_airports_icao (icao_code)
);

CREATE TABLE flights(
    id int auto_increment primary key,
    flight_number varchar(255) not null,
    callsign varchar(255) not null,
    airline_id int not null,
    origin_airport_id int not null,
    destination_airport_id int not null,
    aircraft_id int null,
    status varchar(50) not null,
    scheduled_departure datetime not null,
    scheduled_arrival datetime not null,
    estimated_departure datetime not null,
    estimated_arrival datetime not null,
    actual_departure datetime not null,
    actual_arrival datetime not null,
    base_price decimal(10, 2) not null,

    CONSTRAINT fk_flights_airline
        FOREIGN KEY (airline_id) REFERENCES airlines(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,

    CONSTRAINT fk_flights_origin
        FOREIGN KEY (origin_airport_id) REFERENCES airports(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,

    CONSTRAINT fk_flights_destination
        FOREIGN KEY (destination_airport_id) REFERENCES airports(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,

    CONSTRAINT fk_flights_aircraft
        FOREIGN KEY (aircraft_id) REFERENCES aircraft(id)
        ON UPDATE CASCADE ON DELETE SET NULL,

    KEY idx_flights_status (status),
    KEY idx_flights_sched_dep (scheduled_departure),
    KEY idx_flights_airline (airline_id),
    KEY idx_flights_route (origin_airport_id, destination_airport_id)
);

CREATE TABLE positions(
    id bigint auto_increment primary key,
    flight_id int not null,
    recorded_at datetime not null,
    lat double not null,
    lon double not null,
    altitude int null,
    speed int null,
    heading int null,

    CONSTRAINT fk_positions_flight
        FOREIGN KEY (flight_id) REFERENCES flights(id)
        ON UPDATE CASCADE ON DELETE CASCADE,

    KEY idx_positions_flight_time (flight_id, recorded_at)
);

CREATE TABLE aircraft(
    id int auto_increment primary key,
    model varchar(255) not null,
    registration varchar(255) not null,
    icao_type varchar(10) not null,
    seat_capacity int not null,

    UNIQUE KEY uq_aircraft_reg (registration)
);

CREATE TABLE routes(
    id int auto_increment primary key,
    origin_airport_id int not null,
    destination_airport_id int not null,
    distance_km int not null,

    CONSTRAINT fk_routes_origin
        FOREIGN KEY (origin_airport_id) REFERENCES airports(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,

    CONSTRAINT fk_routes_destination
        FOREIGN KEY (destination_airport_id) REFERENCES airports(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,

    UNIQUE KEY uq_routes_pair (origin_airport_id, destination_airport_id)
);

CREATE TABLE users(
    id int auto_increment primary key,
    name varchar(255) not null,
    email varchar(255) not null,
    password_hash varchar(255) not null,
    role varchar(50) not null default 'user',
    created_at datetime not null default current_timestamp,

    UNIQUE KEY uq_users_email (email)
);

CREATE TABLE bookings(
    id int auto_increment primary key,
    flight_id int not null,
    user_id int not null,
    price decimal(10, 2) not null,
    status varchar(50) not null,
    created_at datetime not null default current_timestamp,

    CONSTRAINT fk_bookings_flight
        FOREIGN KEY (flight_id) REFERENCES flights(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,

    CONSTRAINT fk_bookings_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,

    KEY idx_bookings_flight (flight_id),
    KEY idx_bookings_user (user_id)
);

INSERT INTO airlines (name, iata_code, icao_code, country) VALUES
('Wizz Air', 'W6', 'WZZ', 'Hungary'),
('Ryanair', 'FR', 'RYR', 'Ireland'),
('flydubai', 'FZ', 'FDB', 'United Arab Emirates'),
('Turkish Airlines', 'TK', 'THY', 'Turkey'),
('HiSky', 'H4', 'HYS', 'Moldova'),
('TAROM', 'RO', 'ROT', 'Romania'),
('Qatar Airways', 'QR', 'QTR', 'Qatar'),
('Emirates', 'EK', 'UAE', 'United Arab Emirates');

INSERT INTO airports (name, iata_code, icao_code, city, country, lat, lon) VALUES
('Henri Coandă', 'OTP', 'LROP', 'București', 'România', 44.5711, 26.0850),
('Charles de Gaulle', 'CDG', 'LFPG', 'Paris', 'Franța', 49.0097, 2.5479),
('Heathrow', 'LHR', 'EGLL', 'Londra', 'Regatul Unit', 51.4700, -0.4543),
('Aeroportul Adolfo Suárez Madrid–Barajas', 'MAD', 'LEMD', 'Madrid', 'Spania', 40.4983, -3.5676),
('Aeroportul Internațional Dubai', 'DXB', 'OMDB', 'Dubai', 'Emiratele Arabe Unite', 25.2532, 55.3657),
('Aeroportul Internațional Abu Dhabi', 'AUH', 'OMAA', 'Abu Dhabi', 'Emiratele Arabe Unite', 24.4539, 54.3773),
('Aeroportul Internațional Hamad', 'DOH', 'OTHH', 'Doha', 'Qatar', 25.2731, 51.6081),
('Aeroportul Amsterdam Schiphol', 'AMS', 'EHAM', 'Amsterdam', 'Țările de Jos', 52.3105, 4.7683),
('Aeroportul Leonardo da Vinci–Fiumicino', 'FCO', 'LIRF', 'Roma', 'Italia', 41.8003, 12.2389),
('Aeroportul Istanbul', 'IST', 'LTFM', 'Istanbul', 'Turcia', 41.2753, 28.7519),
('Aeroportul Internațional Heraklion', 'HER', 'LGIR', 'Heraklion', 'Grecia', 35.3397, 25.1803),
('Aeroportul Zurich', 'ZRH', 'LSZH', 'Zurich', 'Elveția', 47.4581, 8.5555),
('Aeroportul Frankfurt', 'FRA', 'EDDF', 'Frankfurt', 'Germania', 50.0379, 8.5622),
('Aeroportul Internațional Malta', 'MLA', 'LMML', 'Luqa', 'Malta', 35.8575, 14.4775),
('Aeroportul Internațional Chișinău', 'RMO', 'LUKK', 'Chișinău', 'Moldova', 46.9277, 28.9309),
('Aeroportul Chopin Varșovia', 'WAW', 'EPWA', 'Varșovia', 'Polonia', 52.1657, 20.9671),
('Aeroportul Václav Havel Praga', 'PRG', 'LKPR', 'Praga', 'Republica Cehă', 50.1008, 14.2632);

INSERT INTO aircraft (model, registration, icao_type, seat_capacity) VALUES
('Airbus A320', 'HA-LPA', 'A320', 180),
('Boeing 737-800', 'EI-DCL', 'B738', 189),
('Boeing 737 MAX 8', 'A6-FMA', 'B38M', 178),
('Airbus A321neo', 'TC-JSR', 'A21N', 220),
('Embraer E195-E2', '9H-ELP', 'E195', 132);

INSERT INTO flights (
  flight_number, callsign, airline_id, origin_airport_id, destination_airport_id,
  aircraft_id, status, scheduled_departure, scheduled_arrival,
  estimated_departure, estimated_arrival, actual_departure, actual_arrival
) VALUES
('W6 5276', 'WIZZ5276', 1, 1, 14, 1, 'Active', '2026-07-01 10:00:00', '2026-07-01 12:20:00', '2026-07-01 10:05:00', '2026-07-01 12:25:00', '2026-07-01 10:05:00', '2026-07-01 12:25:00', 120.00),
('FR 9897', 'RYR9897', 2, 3, 4, 2, 'Active', '2026-07-02 14:00:00', '2026-07-02 15:21:00', '2026-07-02 14:09:00', '2026-07-02 15:40:00', '2026-07-02 14:09:00', '2026-07-02 15:40:00', 95.00),
('FZ 3201', 'FDB3201', 3, 5, 6, 3, 'Active', '2026-07-03 08:00:00', '2026-07-03 15:10:00', '2026-07-03 08:15:00', '2026-07-03 15:25:00', '2026-07-03 08:15:00', '2026-07-03 15:25:00', 210.00),
('TK 4287', 'THY4287', 4, 7, 8, 1, 'Active', '2026-07-04 18:00:00', '2026-07-04 00:40:00', '2026-07-04 18:20:00', '2026-07-04 01:00:00', '2026-07-04 18:20:00', '2026-07-04 01:00:00', 340.00),
('H4 3291', 'HYS3291', 5, 9, 10, 4, 'Active', '2026-07-05 12:00:00', '2026-07-05 18:50:00', '2026-07-05 12:30:00', '2026-07-05 19:20:00', '2026-07-05 12:30:00', '2026-07-05 19:20:00', 180.00),
('RO 1234', 'ROT1234', 6, 1, 2, 2, 'Completed', '2026-03-06 09:00:00', '2026-03-06 11:30:00', '2026-03-06 09:00:00', '2026-03-06 11:30:00', '2026-03-06 09:00:00', '2026-03-06 11:30:00', 170.00),
('QR 5678', 'QTR5678', 7, 3, 4, 3, 'Completed', '2026-03-07 13:00:00', '2026-03-07 14:45:00', '2026-03-07 13:00:00', '2026-03-07 14:45:00', '2026-03-07 13:00:00', '2026-03-07 14:45:00', 560.00),
('EK 9012', 'UAE9012', 8, 5, 6, 4, 'Completed', '2026-03-08 17:00:00', '2026-03-08 23:30:00', '2026-03-08 17:00:00', '2026-03-08 23:30:00','2026-03-08 17:00:00', '2026-03-08 23:30:00', 710.00),
('FR 9898', 'RYR9898', 2, 4, 3, 2, 'Cancelled', '2026-07-11 14:00:00', '2026-07-11 15:21:00', '2026-07-11 14:00:00', '2026-07-11 15:21:00', '2026-07-11 14:00:00', '2026-07-11 15:21:00', 100.00),
('FZ 3202', 'FDB3202', 3, 6, 5, 3, 'Cancelled', '2026-07-12 08:00:00', '2026-07-12 15:10:00', '2026-07-12 08:00:00', '2026-07-12 15:10:00', '2026-07-12 08:00:00', '2026-07-12 15:10:00', 205.00),
('W6 5277', 'WIZZ5277', 1, 14, 1, 1, 'Cancelled', '2026-07-10 10:00:00', '2026-07-10 12:20:00', '2026-07-10 10:05:00', '2026-07-10 12:25:00', '2026-07-10 10:05:00', '2026-07-10 12:25:00', 125.00);

INSERT INTO bookings (flight_id, user_id, price, status, created_at) VALUES
(1, 1, 89.99, 'Paid', '2026-03-01 10:15:00'),
(1, 2, 95.50, 'Paid', '2026-03-01 11:20:00'),
(1, 3, 110.00, 'Paid', '2026-03-02 09:10:00'),
(1, 1, 75.00, 'Pending', '2026-03-02 12:00:00'),
(2, 2, 60.00, 'Paid', '2026-03-03 08:30:00'),
(2, 3, 72.00, 'Paid', '2026-03-03 09:45:00'),
(2, 1, 65.00, 'Cancelled', '2026-03-03 10:10:00'),
(3, 1, 180.00, 'Paid', '2026-03-04 14:20:00'),
(3, 2, 210.00, 'Paid', '2026-03-04 15:40:00'),
(4, 3, 320.00, 'Paid', '2026-03-05 16:00:00'),
(4, 1, 290.00, 'Paid', '2026-03-05 17:15:00'),
(5, 2, 140.00, 'Paid', '2026-03-06 11:10:00'),
(6, 1, 150.00, 'Paid', '2026-03-06 09:20:00'),
(6, 3, 170.00, 'Paid', '2026-03-06 09:40:00'),
(7, 2, 550.00, 'Paid', '2026-03-07 12:00:00'),
(7, 3, 600.00, 'Paid', '2026-03-07 12:30:00'),
(8, 1, 700.00, 'Paid', '2026-03-08 18:00:00'),
(8, 2, 720.00, 'Paid', '2026-03-08 18:25:00'),
(1, 2, 85.00, 'Paid', '2026-03-09 10:00:00'),
(2, 1, 68.00, 'Paid', '2026-03-09 11:30:00'),
(3, 3, 195.00, 'Paid', '2026-03-10 13:45:00');

INSERT INTO users (name, email, password_hash, role) VALUES
('Dana Serbanescu', 'dana@example.com', 'hash1', 'admin'),
('Ana Popescu', 'ana@example.com', 'hash2', 'user'),
('Mihai Ionescu', 'mihai@example.com', 'hash3', 'user'),
('Ioana Georgescu', 'ioana@example.com', 'hash4', 'user');

ALTER TABLE flights ADD base_price DECIMAL(10,2) NOT NULL DEFAULT 0;
UPDATE flights SET base_price = 120.00 WHERE flight_number = 'W6 5276';
UPDATE flights SET base_price = 95.00  WHERE flight_number = 'FR 9897';
UPDATE flights SET base_price = 210.00 WHERE flight_number = 'FZ 3201';
UPDATE flights SET base_price = 340.00 WHERE flight_number = 'TK 4287';
UPDATE flights SET base_price = 180.00 WHERE flight_number = 'H4 3291';
UPDATE flights SET base_price = 170.00 WHERE flight_number = 'RO 1234';
UPDATE flights SET base_price = 560.00 WHERE flight_number = 'QR 5678';
UPDATE flights SET base_price = 710.00 WHERE flight_number = 'EK 9012';
UPDATE flights SET base_price = 100.00 WHERE flight_number = 'FR 9898';
UPDATE flights SET base_price = 205.00 WHERE flight_number = 'FZ 3202';
UPDATE flights SET base_price = 125.00 WHERE flight_number = 'W6 5277';

CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    table_name VARCHAR(50) NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE telemetry (
    id INT AUTO_INCREMENT PRIMARY KEY,
    flight_id INT NOT NULL,
    recorded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    signal_strength INT NOT NULL,
    latency_ms INT NOT NULL,
    packet_loss DECIMAL(5,2) NOT NULL,
    frequency_band VARCHAR(50) NOT NULL,
    connection_status VARCHAR(50) NOT NULL,

    FOREIGN KEY (flight_id) REFERENCES flights(id)
    ON DELETE CASCADE
);

CREATE TABLE email_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_email VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    status VARCHAR(50) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE radar_stations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    airport_id INT NOT NULL,
    radar_name VARCHAR(100) NOT NULL,
    lat DECIMAL(10,6) NOT NULL,
    lon DECIMAL(10,6) NOT NULL,
    range_km INT NOT NULL DEFAULT 600,
    frequency VARCHAR(50) DEFAULT '1030 / 1090 MHz',

    FOREIGN KEY (airport_id) REFERENCES airports(id)
    ON DELETE CASCADE
);

INSERT INTO radar_stations (
    airport_id,
    radar_name,
    lat,
    lon,
    range_km,
    frequency
)
SELECT
    id,
    CONCAT(iata_code, ' Radar Station'),
    lat,
    lon,
    600,
    '1030 / 1090 MHz'
FROM airports;

CREATE TABLE radar_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    flight_id INT NOT NULL,
    radar_name VARCHAR(100) NOT NULL,
    distance_km DECIMAL(10,2) NOT NULL,
    azimuth_deg DECIMAL(10,2) NOT NULL,
    detected_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (flight_id) REFERENCES flights(id)
    ON DELETE CASCADE
);

RENAME TABLE email_logs TO email;
RENAME TABLE radar_logs TO radar;

ALTER TABLE radar
ADD COLUMN detection_status VARCHAR(50);

CREATE TABLE alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    flight_id INT NOT NULL,
    priority VARCHAR(20) NOT NULL,
    score INT NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (flight_id) REFERENCES flights(id) ON DELETE CASCADE
);