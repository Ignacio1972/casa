PRAGMA foreign_keys=OFF;
BEGIN TRANSACTION;
CREATE TABLE ai_suggestions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    context TEXT NOT NULL,
    tone VARCHAR(50),
    duration INTEGER,
    keywords TEXT,
    suggestion_text TEXT NOT NULL,
    category VARCHAR(50),
    used BOOLEAN DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
INSERT INTO ai_suggestions VALUES(1,'tomates','profesional',5,'[]','Array','sin_categoria',0,'2025-09-14 02:41:09','2025-09-14 02:41:09');
INSERT INTO ai_suggestions VALUES(2,'tomates','profesional',5,'[]','Array','sin_categoria',0,'2025-09-14 02:41:09','2025-09-14 02:41:09');
INSERT INTO ai_suggestions VALUES(3,'tomates','profesional',5,'[]','Array','sin_categoria',0,'2025-09-14 02:41:09','2025-09-14 02:41:09');
INSERT INTO ai_suggestions VALUES(4,'Genera UNA alternativa diferente para: "Tomates Líder: Calidad superior en cada tomate. Id..."','profesional',5,'[]','Array','sin_categoria',0,'2025-09-14 02:41:18','2025-09-14 02:41:18');
INSERT INTO ai_suggestions VALUES(5,'Genera UNA alternativa diferente para: "Tomates Líder: Calidad superior en cada tomate. Id..."','profesional',5,'[]','Array','sin_categoria',0,'2025-09-14 02:41:18','2025-09-14 02:41:18');
INSERT INTO ai_suggestions VALUES(6,'Genera UNA alternativa diferente para: "Tomates Líder: Calidad superior en cada tomate. Id..."','profesional',5,'[]','Array','sin_categoria',0,'2025-09-14 02:41:18','2025-09-14 02:41:18');
INSERT INTO ai_suggestions VALUES(7,'Genera UNA alternativa diferente para: "Tomates Líder: Sabor y nutrición en cada bite. Nue..."','profesional',5,'[]','Array','sin_categoria',0,'2025-09-14 02:41:24','2025-09-14 02:41:24');
INSERT INTO ai_suggestions VALUES(8,'Genera UNA alternativa diferente para: "Tomates Líder: Sabor y nutrición en cada bite. Nue..."','profesional',5,'[]','Array','sin_categoria',0,'2025-09-14 02:41:24','2025-09-14 02:41:24');
INSERT INTO ai_suggestions VALUES(9,'Genera UNA alternativa diferente para: "Tomates Líder: Sabor y nutrición en cada bite. Nue..."','profesional',5,'[]','Array','sin_categoria',0,'2025-09-14 02:41:24','2025-09-14 02:41:24');
INSERT INTO ai_suggestions VALUES(10,'jabon','profesional',5,'[]','Array','sin_categoria',0,'2025-09-14 02:47:04','2025-09-14 02:47:04');
INSERT INTO ai_suggestions VALUES(11,'jabon','profesional',5,'[]','Array','sin_categoria',0,'2025-09-14 02:47:04','2025-09-14 02:47:04');
INSERT INTO ai_suggestions VALUES(12,'jabon','profesional',5,'[]','Array','sin_categoria',0,'2025-09-14 02:47:04','2025-09-14 02:47:04');
INSERT INTO ai_suggestions VALUES(13,'Genera UNA alternativa diferente para: "Mantén tu hogar impecable con los jabones Líder. C..."','profesional',5,'[]','Array','sin_categoria',0,'2025-09-14 02:47:10','2025-09-14 02:47:10');
INSERT INTO ai_suggestions VALUES(14,'Genera UNA alternativa diferente para: "Mantén tu hogar impecable con los jabones Líder. C..."','profesional',5,'[]','Array','sin_categoria',0,'2025-09-14 02:47:10','2025-09-14 02:47:10');
INSERT INTO ai_suggestions VALUES(15,'Genera UNA alternativa diferente para: "Mantén tu hogar impecable con los jabones Líder. C..."','profesional',5,'[]','Array','sin_categoria',0,'2025-09-14 02:47:10','2025-09-14 02:47:10');
INSERT INTO ai_suggestions VALUES(16,'Genera UNA alternativa diferente para: "Limpieza profunda y cuidado de tus manos con los j..."','profesional',5,'[]','Array','sin_categoria',0,'2025-09-14 02:47:13','2025-09-14 02:47:13');
INSERT INTO ai_suggestions VALUES(17,'Genera UNA alternativa diferente para: "Limpieza profunda y cuidado de tus manos con los j..."','profesional',5,'[]','Array','sin_categoria',0,'2025-09-14 02:47:13','2025-09-14 02:47:13');
INSERT INTO ai_suggestions VALUES(18,'Genera UNA alternativa diferente para: "Limpieza profunda y cuidado de tus manos con los j..."','profesional',5,'[]','Array','sin_categoria',0,'2025-09-14 02:47:13','2025-09-14 02:47:13');
INSERT INTO ai_suggestions VALUES(19,'Genera UNA alternativa diferente para: "Disfruta de la limpieza con los jabones Líder. Cal..."','profesional',5,'[]','Array','sin_categoria',0,'2025-09-14 02:47:20','2025-09-14 02:47:20');
INSERT INTO ai_suggestions VALUES(20,'Genera UNA alternativa diferente para: "Disfruta de la limpieza con los jabones Líder. Cal..."','profesional',5,'[]','Array','sin_categoria',0,'2025-09-14 02:47:20','2025-09-14 02:47:20');
INSERT INTO ai_suggestions VALUES(21,'Genera UNA alternativa diferente para: "Disfruta de la limpieza con los jabones Líder. Cal..."','profesional',5,'[]','Array','sin_categoria',0,'2025-09-14 02:47:20','2025-09-14 02:47:20');
COMMIT;
