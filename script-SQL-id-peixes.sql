CREATE DATABASE IF NOT EXISTS fishid
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;
USE fishid;

-- 1) Usuários
CREATE TABLE usuarios (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome          VARCHAR(120)        NOT NULL,
  email         VARCHAR(160)        NOT NULL UNIQUE,
  telefone      VARCHAR(30),
  senha_hash    VARCHAR(255)        NOT NULL,      -- guarde hash da senha
  criado_em     DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2) Endereços (1:1 com usuários; PK = FK)
CREATE TABLE enderecos (
  usuario_id    INT UNSIGNED        NOT NULL,
  cep           VARCHAR(15)         NOT NULL,
  rua           VARCHAR(160)        NOT NULL,
  numero        VARCHAR(20)         NOT NULL,
  complemento   VARCHAR(120),
  cidade        VARCHAR(120)        NOT NULL,
  estado        VARCHAR(2)          NOT NULL,
  PRIMARY KEY (usuario_id),
  CONSTRAINT fk_end_usuario
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- 3) Peixes (catálogo taxonômico)
CREATE TABLE peixes (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome_cientifico    VARCHAR(160)   NOT NULL,
  nome_comum         VARCHAR(160),
  reino              VARCHAR(80),
  filo               VARCHAR(80),
  classe             VARCHAR(80),
  ordem              VARCHAR(80),
  familia            VARCHAR(80),
  genero             VARCHAR(80),
  especie            VARCHAR(80),
  informacoes        TEXT,
  UNIQUE KEY uk_peixe_nome_cientifico (nome_cientifico),
  KEY idx_peixe_nome_comum (nome_comum)
) ENGINE=InnoDB;

-- 4) Histórico (registra cada imagem enviada e o resultado da IA)
CREATE TABLE historico (
  id                        BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id                INT UNSIGNED      NOT NULL,
  peixe_id                  INT UNSIGNED      NULL,   -- se a IA casar com catálogo
  nome_cientifico_predito   VARCHAR(160)      NULL,   -- fallback textual (se não achar no catálogo)
  confianca                 DECIMAL(5,4)      NULL,   -- ex.: 0.9735
  caminho_imagem            VARCHAR(500)      NOT NULL,
  confirmado                TINYINT(1)        NOT NULL DEFAULT 0,  -- usuário confirmou/corrigiu?
  criado_em                 DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_hist_usuario
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_hist_peixe
    FOREIGN KEY (peixe_id) REFERENCES peixes(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  KEY idx_hist_usuario_data (usuario_id, criado_em),
  KEY idx_hist_peixe (peixe_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS password_resets (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id       INT UNSIGNED NOT NULL,
  selector      CHAR(16)      NOT NULL,
  verifier_hash CHAR(64)      NOT NULL,
  expires_at    DATETIME      NOT NULL,
  used          TINYINT(1)    NOT NULL DEFAULT 0,
  created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_selector (selector),
  FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;


select * from usuarios;
select * from enderecos;
select * from peixes;
select * from historico;
Alter table historico delete where usuario_id = "1";
delete from historico where usuario_id = "1";

-- Codificação para suportar caracteres especiais (traços, acentos etc.)
SET NAMES utf8mb4;
START TRANSACTION;

INSERT INTO peixes
  (nome_cientifico, nome_comum, reino, filo, classe, ordem, familia, genero, especie, informacoes)
VALUES
-- 1
('Ancistrus_albino','cascudinho-bristlenose (albino)','Animalia','Chordata','Actinopterygii','Siluriformes','Loricariidae','Ancistrus','sp.',
'Tamanho médio 8–12 cm (máx. ~15 cm); peso médio 20–80 g. Distribuição: gênero amplamente distribuído na América do Sul, com espécies no Alto Paraná/Paranapanema. Habitat: riachos de correnteza, fundo rochoso e troncos. Dieta: raspador de algas/perifíton e detritos. Características: placas ósseas, boca em ventosa; machos com tentáculos cefálicos. Reprodução: primavera–verão; ninhos em cavidades e cuidado parental. Período de pesca/defeso: normalmente segue o defeso geral da piracema na bacia (ex.: SP/PR ~outubro–fevereiro; confirmar portarias locais). Importância ecológica: controla biofilme/algas, sensível à poluição e assoreamento. Observação: ‘albino’ é morfo de cativeiro; em campo registrar como Ancistrus sp.'),
-- 2
('Astyanax_altiparanae','lambari-do-rabo-amarelo','Animalia','Chordata','Actinopterygii','Characiformes','Characidae','Astyanax','altiparanae',
'Tamanho médio 8–12 cm (máx. ~15 cm); peso médio 15–40 g. Distribuição: bacia do Alto Paraná. Habitat: margens, remansos e pequenos cursos com vegetação. Dieta: onívoro oportunista (insetos, frutos/sementes, algas). Características: forma cardumes; importante como presa de predadores. Reprodução: primavera–verão (pico na estação chuvosa). Período de pesca/defeso: geralmente abrangido pelo defeso da piracema na bacia (~out–fev; verificar regras e tamanhos mínimos). Importância ecológica: dispersão de sementes, elo trófico chave; tolera certo grau de alteração ambiental.'),
-- 3
('Bryconamericus_iheringii','lambari (piquira)','Animalia','Chordata','Actinopterygii','Characiformes','Characidae','Bryconamericus','iheringii',
'Tamanho médio 5–8 cm (máx. ~10 cm); peso médio 5–15 g. Distribuição: Sudeste/Sul do Brasil e Uruguai; presente no Alto Paraná. Habitat: riachos com correnteza moderada; margem e meia-água. Dieta: principalmente insetívoro. Reprodução: primavera–verão. Período de pesca/defeso: em geral segue piracema da bacia; conferir normas locais. Importância ecológica: controla insetos aquáticos; presa de peixes maiores; sensível a fragmentação de habitats.'),
-- 4
('Characidium_zebra','charutinho/bocarra-zebra','Animalia','Chordata','Actinopterygii','Characiformes','Crenuchidae','Characidium','zebra',
'Tamanho médio 4–6 cm (máx. ~7 cm); peso médio 2–5 g. Distribuição: bacias do Sudeste/Sul, incluindo Alto Paraná. Habitat: trechos rasos e pedregosos (reofílico). Dieta: insetívoro bentônico. Características: corpo alongado com listras escuras; se apoia no substrato. Reprodução: primavera–verão. Período de pesca/defeso: segue regra geral da piracema; checar portaria vigente. Importância ecológica: indicador de trechos de corredeira preservados; sensível a assoreamento.'),
-- 5
('Cichlasoma_paranaense','acará (ciclídeo do Paraná)','Animalia','Chordata','Actinopterygii','Cichliformes','Cichlidae','Cichlasoma','paranaense',
'Tamanho médio 10–15 cm (máx. ~18 cm); peso médio 80–200 g. Distribuição: bacia do Paraná. Habitat: margens e remansos com refúgios. Dieta: onívoro. Características: territorial; cuidado parental com ovos/alevinos. Reprodução: primavera–verão. Período de pesca/defeso: costuma seguir defeso geral da piracema (verificar regras estaduais). Importância ecológica: controla invertebrados e pequenos peixes; pode competir em ambientes alterados.'),
-- 6
('Crenicichla_britskii','jacundá (ciclídeo)','Animalia','Chordata','Actinopterygii','Cichliformes','Cichlidae','Crenicichla','britskii',
'Tamanho médio 18–22 cm (máx. ~28 cm); peso médio 150–400 g. Distribuição: bacia do Paraná. Habitat: margens com estruturas (troncos/pedras). Dieta: piscívoro/insetívoro; predador de emboscada. Reprodução: primavera–verão; desova em tocas com cuidado parental. Período de pesca/defeso: segue piracema local; confirmar portarias. Importância ecológica: regula populações de pequenos peixes; sensível à perda de abrigos.'),
-- 7
('Crenicichla_haroldoi','jacundá (ciclídeo)','Animalia','Chordata','Actinopterygii','Cichliformes','Cichlidae','Crenicichla','haroldoi',
'Tamanho médio 12–18 cm (máx. ~22 cm); peso médio 80–250 g. Distribuição: bacia do Paraná. Habitat: margens com vegetação/refúgios. Dieta: piscívoro/insetívoro. Reprodução: primavera–verão; cuidado parental. Período de pesca/defeso: em geral sob piracema; checar norma local. Importância ecológica: predador intermediário; dependente de micro-hábitats estruturados.'),
-- 8
('Geophagus_brasiliensis','acará-terra (cará)','Animalia','Chordata','Actinopterygii','Cichliformes','Cichlidae','Geophagus','brasiliensis',
'Tamanho médio 12–20 cm (máx. ~28 cm); peso médio 150–300 g. Distribuição: Sudeste/Sul do Brasil (inclui bacia do Paraná). Habitat: trechos lentos/remansos com fundo arenoso. Dieta: onívoro bentívoro (peneira sedimento). Características: cava o substrato; coloração variável; cuidado parental. Reprodução: primavera–verão. Período de pesca/defeso: normalmente sob regras gerais de piracema (verificar portarias). Importância ecológica: bioturbação do sedimento; pode tolerar ambientes alterados moderadamente.'),
-- 9
('Gymnotus_inaequilabiatus','tuvira (morena)','Animalia','Chordata','Actinopterygii','Gymnotiformes','Gymnotidae','Gymnotus','inaequilabiatus',
'Tamanho médio 30–45 cm (máx. ~60 cm); peso médio 200–600 g. Distribuição: bacias do Paraguai e Paraná (inclui Paranapanema). Habitat: margens com vegetação, águas calmas com abrigos. Dieta: insetos aquáticos, pequenos peixes e invertebrados. Características: peixe-elétrico fraco; corpo alongado, sem nadadeira dorsal; ativo ao crepúsculo/noite. Reprodução: estação chuvosa (geralmente primavera–verão). Período de pesca/defeso: normalmente coberto pelo defeso geral da piracema na bacia (~out–fev; confirmar portarias). Importância ecológica: predador intermediário; sensível à poluição e perda de vegetação ripária.'),
-- 10
('Hoplias_Malabaricus','traíra','Animalia','Chordata','Actinopterygii','Characiformes','Erythrinidae','Hoplias','malabaricus',
'Tamanho médio 25–40 cm (máx. ~60 cm); peso médio 0,8–1,5 kg. Distribuição: ampla na América do Sul; comum no Alto Paraná. Habitat: remansos, lagoas, margens com vegetação. Dieta: piscívoro; predador de emboscada. Características: dentição forte; comportamento territorial. Reprodução: primavera–verão (chuvas); cuidado com ninhos/alevinos. Período de pesca/defeso: usualmente abrangida pelo defeso da piracema (~out–fev; verificar normas locais e tamanhos mínimos). Importância ecológica: regula populações de pequenos peixes; muito valorizada na pesca artesanal/esportiva.'),
-- 11
('Hoplosternum_littorale','tamoatá (caborja)','Animalia','Chordata','Actinopterygii','Siluriformes','Callichthyidae','Hoplosternum','littorale',
'Tamanho médio 12–18 cm (máx. ~24 cm); peso médio 80–200 g. Distribuição: Amazônia, Guianas e Paraguai; registros no Alto Paraná são pontuais. Habitat: águas calmas com vegetação; tolera hipóxia e pode respirar ar. Dieta: onívoro/detritívoro. Características: placas ósseas; comportamento de construir ninhos com bolhas. Reprodução: estação chuvosa. Período de pesca/defeso: geralmente segue o defeso de piracema quando aplicável; confirmar portaria local. Importância ecológica: reciclagem de matéria orgânica; resiliente a variações ambientais.'),
-- 12
('Hypostomus_ancistroides','cascudo','Animalia','Chordata','Actinopterygii','Siluriformes','Loricariidae','Hypostomus','ancistroides',
'Tamanho médio 12–18 cm (máx. ~25 cm); peso médio 120–250 g. Distribuição: bacia do Paraná. Habitat: riachos com substrato rochoso e correnteza. Dieta: raspador/detritívoro (algas, perifíton). Características: placas ósseas; boca em ventosa; ativo ao entardecer/noite. Reprodução: primavera–verão; desova em cavidades. Período de pesca/defeso: em geral sujeito ao defeso da piracema; checar regras. Importância ecológica: controle de biofilme e ciclagem de nutrientes; sensível a assoreamento.'),
-- 13
('Imparfinis_schubarti','bagre (heptapterídeo)','Animalia','Chordata','Actinopterygii','Siluriformes','Heptapteridae','Imparfinis','schubarti',
'Tamanho médio 9–14 cm (máx. ~16 cm); peso médio 20–60 g. Distribuição: Sudeste/Sul do Brasil (Alto Paraná). Habitat: riachos com correnteza e fundo de cascalho. Dieta: insetívoro; ativo principalmente à noite. Características: barbilhões longos; corpo esguio. Reprodução: primavera–verão. Período de pesca/defeso: segue defeso da piracema quando aplicável; confirmar normas. Importância ecológica: predador de invertebrados bentônicos; sensível à poluição.'),
-- 14
('Leporinus_striatus','piau-listrado','Animalia','Chordata','Actinopterygii','Characiformes','Anostomidae','Leporinus','striatus',
'Tamanho médio 15–25 cm (máx. ~30 cm); peso médio 200–600 g. Distribuição: bacia do Paraná e adjacências. Habitat: rios de médio porte; margens e corredeiras. Dieta: onívoro (algas, invertebrados, frutos). Características: boca inferior; listras corporais marcantes. Reprodução: primavera–verão (pico nov–fev). Período de pesca/defeso: normalmente protegido pela piracema (confirmar tamanhos/cotas locais). Importância ecológica: consome perifíton e frutos; presa de peixes maiores.'),
-- 15
('Oligosarcus_paranensis','peixe-cachorro (oligosarcus)','Animalia','Chordata','Actinopterygii','Characiformes','Characidae','Oligosarcus','paranensis',
'Tamanho médio 18–22 cm (máx. ~28 cm); peso médio 150–350 g. Distribuição: bacia do Paraná. Habitat: águas abertas de rios e reservatórios. Dieta: piscívoro; predador ativo de meia-água. Características: mandíbula alongada; nado rápido. Reprodução: primavera–verão. Período de pesca/defeso: geralmente sujeito ao defeso da piracema; confirmar portarias. Importância ecológica: regula populações de pequenos peixes; sensível à sobrepesca local.'),
-- 16
('Phalloceros_caudimaculatus','barrigudinho (cauda-mancha)','Animalia','Chordata','Actinopterygii','Cyprinodontiformes','Poeciliidae','Phalloceros','caudimaculatus',
'Tamanho médio 3–5 cm (máx. ~6 cm); peso médio 1–3 g. Distribuição: Sudeste do Brasil; amplamente translocado e comum no Alto Paraná. Habitat: águas calmas e vegetadas; tolerante a variações ambientais. Dieta: onívoro (algas e microinvertebrados). Características: vivíparo; alta fecundidade e reprodução contínua. Reprodução: o ano todo, com picos na primavera–verão. Período de pesca/defeso: em geral sem defeso específico; populações podem até ser objeto de manejo/controle. Importância ecológica: recurso alimentar para predadores; pode competir com espécies nativas em ambientes alterados.'),
-- 17
('Poecilia_reticulada','barrigudinho','Animalia','Chordata','Actinopterygii','Cyprinodontiformes','Poeciliidae','Poecilia','reticulata',
'Tamanho médio 2–4 cm (♂), 3–5 cm (♀); máx. ~6 cm; peso médio 0,5–2 g. Distribuição: nativo do Caribe/norte da América do Sul; amplamente introduzido (exótica) no Brasil, inclusive no Alto Paraná. Habitat: águas rasas e calmas; tolera variações ambientais. Dieta: onívoro. Características: vivíparo; alta taxa reprodutiva; plasticidade fenotípica. Reprodução: o ano todo (picos na primavera–verão). Período de pesca/defeso: geralmente sem defeso específico por ser espécie exótica. Importância ecológica: pode competir com nativos e alterar comunidades de macroinvertebrados; usado no controle de larvas de mosquito.'),
-- 18
('Poecilia_vivipara','barrigudinho','Animalia','Chordata','Actinopterygii','Cyprinodontiformes','Poeciliidae','Poecilia','vivipara',
'Tamanho médio 4–6 cm (máx. ~8 cm); peso médio 2–5 g. Distribuição: drenagens costeiras do Brasil; frequentemente translocada para interiores (pode ocorrer no Alto Paraná). Habitat: águas calmas, inclusive salobras; tolerante a variações ambientais. Dieta: onívoro (algas e microinvertebrados). Características: vivípara; reprodução contínua. Reprodução: ano todo com picos na primavera–verão. Período de pesca/defeso: tipicamente sem defeso específico; onde nativa, não há fechamento direcionado. Importância ecológica: presa para predadores; pode competir com pequenos caracídeos.'),
-- 19
('Psalidodon_bockmanni','lambari (ex-Astyanax)','Animalia','Chordata','Actinopterygii','Characiformes','Characidae','Psalidodon','bockmanni',
'Tamanho médio 7–10 cm (máx. ~12 cm); peso médio 10–25 g. Distribuição: Alto Paraná. Habitat: riachos e margens de rios. Dieta: onívoro/insetívoro. Características: forma cardumes; faixa lateral prateada. Reprodução: primavera–verão. Período de pesca/defeso: em geral protegido pelo defeso da piracema (~out–fev; checar portaria da bacia). Importância ecológica: elo trófico chave e presa de predadores.'),
-- 20
('Rhamdia_quelen','jundiá/bagre','Animalia','Chordata','Actinopterygii','Siluriformes','Heptapteridae','Rhamdia','quelen',
'Tamanho médio 25–40 cm (máx. ~60 cm); peso médio 0,5–1,2 kg. Distribuição: ampla no Sul/Sudeste do Brasil (inclui Alto Paraná). Habitat: rios e lagoas; noturno, de hábito demersal. Dieta: onívoro/carnívoro (invertebrados e peixes). Características: barbilhões longos; valorizado na pesca artesanal/aquicultura. Reprodução: primavera–verão (chuvas). Período de pesca/defeso: geralmente abrangido pela piracema (confirmar tamanhos/cotas locais). Importância ecológica: predador/omnívoro que recicla nutrientes; sensível à poluição de amônia/baixa oxigenação prolongada.'),
-- 21
('Serrapinnus_notomelas','lambari (cheirodontino)','Animalia','Chordata','Actinopterygii','Characiformes','Characidae','Serrapinnus','notomelas',
'Tamanho médio 3–5 cm (máx. ~6 cm); peso médio 1–3 g. Distribuição: Sudeste/Sul do Brasil; presente no Alto Paraná. Habitat: águas calmas e margens; forma cardumes. Dieta: onívoro (microcrustáceos e algas). Características: pequeno porte; importante na teia trófica de riachos. Reprodução: primavera–verão. Período de pesca/defeso: normalmente sob defeso de piracema; verificar regras locais. Importância ecológica: presa abundante e consumidora de perifíton.'),
-- 22
('Serrasalmus_maculatus','piranha-branca (manchada)','Animalia','Chordata','Actinopterygii','Characiformes','Serrasalmidae','Serrasalmus','maculatus',
'Tamanho médio 18–22 cm (máx. ~28 cm); peso médio 0,3–0,7 kg. Distribuição: bacia do Paraná e outras. Habitat: águas calmas de rios e reservatórios. Dieta: piscívora/necrofágica. Características: dentes serrados; comportamento oportunista. Reprodução: verão (chuvas). Período de pesca/defeso: regra geral de piracema (confirmar portarias/tamanhos). Importância ecológica: remove peixes debilitados e carcaças; regula populações de pequenos peixes.'),
-- 23
('Synbranchus_marmoratus','muçum','Animalia','Chordata','Actinopterygii','Synbranchiformes','Synbranchidae','Synbranchus','marmoratus',
'Tamanho médio 50–70 cm (máx. ~100 cm); peso médio 0,5–1,2 kg. Distribuição: ampla na América do Sul (inclui Alto Paraná). Habitat: banhados e lagoas; suporta baixa oxigenação e pode respirar ar. Dieta: carnívoro (invertebrados e pequenos peixes). Características: corpo serpentiforme; ativo ao crepúsculo/noite. Reprodução: verão. Período de pesca/defeso: costuma seguir regras gerais da bacia (piracema) para proteção reprodutiva; verificar norma local. Importância ecológica: predador de ambientes lênticos; tolerante a variações, mas afetado por poluição severa.'),
-- 24
('Trichomyterus_sp','bagrinho (tricomictérido)','Animalia','Chordata','Actinopterygii','Siluriformes','Trichomycteridae','Trichomycterus','sp.',
'Tamanho médio 7–12 cm (máx. ~15 cm); peso médio 10–40 g. Distribuição: diversas espécies no Sudeste/Sul; várias no Alto Paraná. Habitat: riachos frios/pedregosos, água corrente (reofílico). Dieta: insetívoro bentônico. Características: corpo delgado; hábitos noturnos/crepusculares. Reprodução: primavera–verão. Período de pesca/defeso: geralmente incluído na piracema; confirmar portarias. Importância ecológica: indicador de trechos bem oxigenados; sensível a assoreamento e perda de mata ciliar.')
ON DUPLICATE KEY UPDATE
  nome_comum   = VALUES(nome_comum),
  reino        = VALUES(reino),
  filo         = VALUES(filo),
  classe       = VALUES(classe),
  ordem        = VALUES(ordem),
  familia      = VALUES(familia),
  genero       = VALUES(genero),
  especie      = VALUES(especie),
  informacoes  = VALUES(informacoes);

COMMIT;

INSERT INTO historico
  (usuario_id, peixe_id, nome_cientifico_predito, confianca, caminho_imagem, confirmado, criado_em)
VALUES
  (1, 10, 'Hoplias malabaricus',          0.9873, 'uploads/2025/08/1_1723360001_hop_mal.jpg',          1, NOW() - INTERVAL 6 DAY),
  (1, 20, 'Rhamdia quelen',               0.8750, 'uploads/2025/08/1_1723363602_rha_que.jpg',          0, NOW() - INTERVAL 5 DAY),
  (1, 8,  'Geophagus brasiliensis',       0.9320, 'uploads/2025/08/1_1723367203_geo_bra.jpg',          1, NOW() - INTERVAL 5 DAY + INTERVAL 3 HOUR),
  (1, 22, 'Serrasalmus maculatus',        0.7415, 'uploads/2025/08/1_1723370804_ser_mac.jpg',          0, NOW() - INTERVAL 4 DAY),
  (1, 5,  'Cichlasoma paranaense',        0.8031, 'uploads/2025/08/1_1723374405_cic_par.jpg',          0, NOW() - INTERVAL 4 DAY + INTERVAL 2 HOUR),
  (1, 2,  'Astyanax altiparanae',         0.9144, 'uploads/2025/08/1_1723378006_ast_alt.jpg',          1, NOW() - INTERVAL 3 DAY),
  (1, 21, 'Serrapinnus notomelas',        0.7660, 'uploads/2025/08/1_1723381607_ser_not.jpg',          0, NOW() - INTERVAL 3 DAY + INTERVAL 5 HOUR),
  (1, 12, 'Hypostomus ancistroides',      0.9412, 'uploads/2025/08/1_1723385208_hyp_anc.jpg',          0, NOW() - INTERVAL 2 DAY),
  (1, 24, 'Trichomycterus sp.',           0.8460, 'uploads/2025/08/1_1723388809_tri_sp.jpg',           0, NOW() - INTERVAL 2 DAY + INTERVAL 4 HOUR),
  (1, 9,  'Gymnotus inaequilabiatus',     0.8125, 'uploads/2025/08/1_1723392410_gym_ina.jpg',          0, NOW() - INTERVAL 1 DAY),
  (1, 17, 'Poecilia reticulata',          0.9052, 'uploads/2025/08/1_1723396011_poe_ret.jpg',          1, NOW() - INTERVAL 1 DAY + INTERVAL 1 HOUR),
  (1, 16, 'Phalloceros caudimaculatus',   0.7810, 'uploads/2025/08/1_1723399612_pha_cau.jpg',          0, NOW() - INTERVAL 2 HOUR);


-- 12 exemplos de predições para o usuário 1
INSERT INTO historico
  (usuario_id, peixe_id, nome_cientifico_predito, confianca, caminho_imagem, confirmado, criado_em)
VALUES
  (1, (SELECT id FROM peixes WHERE nome_cientifico='Piaractus mesopotamicus' LIMIT 1),
      'Piaractus mesopotamicus', 0.9640, 'uploads/2025/08/1_1723351001_aa11bb22.jpg', 0, NOW() - INTERVAL 6 DAY),

  (1, (SELECT id FROM peixes WHERE nome_cientifico='Prochilodus lineatus' LIMIT 1),
      'Prochilodus lineatus',    0.9325, 'uploads/2025/08/1_1723354602_cc33dd44.jpg', 1, NOW() - INTERVAL 5 DAY),

  (1, (SELECT id FROM peixes WHERE nome_cientifico='Hoplias malabaricus' LIMIT 1),
      'Hoplias malabaricus',     0.9873, 'uploads/2025/08/1_1723358203_ee55ff66.jpg', 1, NOW() - INTERVAL 5 DAY + INTERVAL 3 HOUR),

  (1, (SELECT id FROM peixes WHERE nome_cientifico='Rhamdia quelen' LIMIT 1),
      'Rhamdia quelen',          0.8750, 'uploads/2025/08/1_1723361804_1122aabb.jpg', 0, NOW() - INTERVAL 4 DAY),

  (1, (SELECT id FROM peixes WHERE nome_cientifico='Hypostomus commersoni' LIMIT 1),
      'Hypostomus commersoni',   0.9412, 'uploads/2025/08/1_1723365405_bb33cc44.jpg', 0, NOW() - INTERVAL 4 DAY + INTERVAL 2 HOUR),

  (1, (SELECT id FROM peixes WHERE nome_cientifico='Cichla kelberi' LIMIT 1),
      'Cichla kelberi',          0.8031, 'uploads/2025/08/1_1723369006_dd55ee66.jpg', 0, NOW() - INTERVAL 3 DAY),

  (1, (SELECT id FROM peixes WHERE nome_cientifico='Geophagus brasiliensis' LIMIT 1),
      'Geophagus brasiliensis',  0.9120, 'uploads/2025/08/1_1723372607_ff778899.jpg', 1, NOW() - INTERVAL 3 DAY + INTERVAL 5 HOUR),

  (1, (SELECT id FROM peixes WHERE nome_cientifico='Serrasalmus maculatus' LIMIT 1),
      'Serrasalmus maculatus',   0.7215, 'uploads/2025/08/1_1723376208_00aa11bb.jpg', 0, NOW() - INTERVAL 2 DAY),

  (1, (SELECT id FROM peixes WHERE nome_cientifico='Brycon orbignyanus' LIMIT 1),
      'Brycon orbignyanus',      0.9688, 'uploads/2025/08/1_1723379809_22cc33dd.jpg', 1, NOW() - INTERVAL 2 DAY + INTERVAL 4 HOUR),

  (1, (SELECT id FROM peixes WHERE nome_cientifico='Loricariichthys platymetopon' LIMIT 1),
      'Loricariichthys platymetopon', 0.8460, 'uploads/2025/08/1_1723383410_44ee55ff.jpg', 0, NOW() - INTERVAL 1 DAY),

  (1, NULL, 'Corydoras paleatus', 0.9052, 'uploads/2025/08/1_1723387011_66778899.jpg', 0, NOW() - INTERVAL 1 DAY + INTERVAL 1 HOUR),

  (1, NULL, 'Steindachneridion scriptum', 0.7810, 'uploads/2025/08/1_1723390612_a1b2c3d4.jpg', 0, NOW() - INTERVAL 2 HOUR);
  
  CREATE TABLE IF NOT EXISTS password_resets (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id       INT UNSIGNED NOT NULL,
  selector      CHAR(16)      NOT NULL,
  verifier_hash CHAR(64)      NOT NULL,
  expires_at    DATETIME      NOT NULL,
  used          TINYINT(1)    NOT NULL DEFAULT 0,
  created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_selector (selector),
  FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

SELECT id, email FROM usuarios WHERE email = 'fabio_ribeiro26@hotmail.com';

select * from password_resets;



SET NAMES utf8mb4;

-- 1) Tabela de imagens por peixe
CREATE TABLE IF NOT EXISTS peixe_imagens (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  peixe_id     INT UNSIGNED      NOT NULL,
  caminho      VARCHAR(512)      NOT NULL,  -- caminho RELATIVO dentro do projeto
  split_set    ENUM('train','test','other') NOT NULL DEFAULT 'train',
  titulo       VARCHAR(160)      NULL,      -- opcional (legenda curta)
  credito      VARCHAR(160)      NULL,      -- opcional (fonte/autor)
  largura_px   INT UNSIGNED      NULL,      -- opcional
  altura_px    INT UNSIGNED      NULL,      -- opcional
  checksum_sha256 CHAR(64)       NULL,      -- opcional p/ deduplicação futura
  ordem        SMALLINT UNSIGNED NOT NULL DEFAULT 0,  -- ordenação manual
  destaque     TINYINT(1)        NOT NULL DEFAULT 0,  -- 1 = preferir na vitrine
  ativo        TINYINT(1)        NOT NULL DEFAULT 1,
  criado_em    DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT fk_peixe_imagens_peixe
    FOREIGN KEY (peixe_id) REFERENCES peixes(id) ON DELETE CASCADE,

  UNIQUE KEY uk_peixe_caminho (peixe_id, caminho),
  KEY idx_peixe_ordem (peixe_id, destaque, ordem, id),
  KEY idx_peixe_split (peixe_id, split_set),
  KEY idx_checksum (checksum_sha256)
) ENGINE=InnoDB;

-- Observação sobre "caminho":
-- Armazene caminhos RELATIVOS, ex.:
-- 'backend/data/train/Hypostomus_ancistroides/IMG_0001.jpg'
-- 'backend/data/test/Hypostomus_ancistroides/img23.png'

-- 2) (Opcional) View com todas as imagens ativas
CREATE OR REPLACE VIEW v_peixe_imagens_ativas AS
SELECT *
FROM peixe_imagens
WHERE ativo = 1;

-- 3) View para pegar as TOP 5 imagens por peixe (prioriza destaque e ordem)
-- Requer MySQL 8+ (usa window function ROW_NUMBER).
CREATE OR REPLACE VIEW v_peixe_top5 AS
SELECT *
FROM (
  SELECT
    pi.*,
    ROW_NUMBER() OVER (
      PARTITION BY pi.peixe_id
      ORDER BY pi.destaque DESC, pi.ordem ASC, pi.id ASC
    ) AS rn
  FROM peixe_imagens pi
  WHERE pi.ativo = 1
) t
WHERE t.rn <= 5;

-- 4) (Opcional) View com contagem de imagens por peixe
CREATE OR REPLACE VIEW v_peixe_imagens_count AS
SELECT peixe_id, COUNT(*) AS total_imagens,
       SUM(CASE WHEN ativo=1 THEN 1 ELSE 0 END) AS total_ativas
FROM peixe_imagens
GROUP BY peixe_id;

-- 5) (Opcional) Tabela de STAGING para carga em massa (preenchida pelo seu script PHP)
-- Você pode popular essa tabela com (nome_cientifico, split, caminho_relativo, ...),
-- e depois transferir para a tabela final com um único INSERT...SELECT.
CREATE TABLE IF NOT EXISTS staging_imagens (
  id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome_cientifico    VARCHAR(160)   NOT NULL,
  split_set          ENUM('train','test','other') NOT NULL,
  caminho            VARCHAR(512)   NOT NULL,  -- relativo dentro do projeto
  titulo             VARCHAR(160)   NULL,
  credito            VARCHAR(160)   NULL,
  checksum_sha256    CHAR(64)       NULL,
  criado_em          DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_staging (nome_cientifico, caminho)
) ENGINE=InnoDB;

-- 6) (Opcional) Upsert da staging para a definitiva (roda DEPOIS de popular staging_imagens)
-- Evita duplicados pela UNIQUE (peixe_id, caminho)
-- Ajuste o caminho base se necessário (já esperamos "backend/data/..." na staging).
-- IMPORTANTE: só roda quando a staging estiver populada.
-- INSERT INTO peixe_imagens (peixe_id, caminho, split_set, titulo, credito, checksum_sha256)
-- SELECT p.id, s.caminho, s.split_set, s.titulo, s.credito, s.checksum_sha256
-- FROM staging_imagens s
-- JOIN peixes p ON p.nome_cientifico = s.nome_cientifico
-- ON DUPLICATE KEY UPDATE
--   split_set = VALUES(split_set),
--   titulo = VALUES(titulo),
--   credito = VALUES(credito),
--   checksum_sha256 = COALESCE(VALUES(checksum_sha256), peixe_imagens.checksum_sha256);


-- garante a tabela (já criada antes)
-- CREATE TABLE peixe_imagens (...);

DELIMITER //
CREATE OR REPLACE PROCEDURE sp_upsert_peixe_imagem(
  IN p_nome_cientifico VARCHAR(160),
  IN p_caminho        VARCHAR(512),
  IN p_split          ENUM('train','test','other'),
  IN p_ordem          SMALLINT UNSIGNED,
  IN p_destaque       TINYINT,
  IN p_largura        INT UNSIGNED,
  IN p_altura         INT UNSIGNED,
  IN p_titulo         VARCHAR(160),
  IN p_credito        VARCHAR(160),
  IN p_checksum       CHAR(64)
)
BEGIN
  DECLARE v_peixe_id INT UNSIGNED;
  SELECT id INTO v_peixe_id
  FROM peixes
  WHERE nome_cientifico = p_nome_cientifico
  LIMIT 1;

  IF v_peixe_id IS NOT NULL THEN
    INSERT INTO peixe_imagens
      (peixe_id, caminho, split_set, ordem, destaque,
       largura_px, altura_px, titulo, credito, checksum_sha256, ativo)
    VALUES
      (v_peixe_id, p_caminho, p_split, p_ordem, p_destaque,
       p_largura, p_altura, p_titulo, p_credito, p_checksum, 1)
    ON DUPLICATE KEY UPDATE
      split_set        = VALUES(split_set),
      ordem            = VALUES(ordem),
      destaque         = GREATEST(peixe_imagens.destaque, VALUES(destaque)),
      largura_px       = VALUES(largura_px),
      altura_px        = VALUES(altura_px),
      titulo           = VALUES(titulo),
      credito          = VALUES(credito),
      checksum_sha256  = COALESCE(VALUES(checksum_sha256), peixe_imagens.checksum_sha256),
      ativo            = 1;
  END IF;
END//
DELIMITER ;

CALL sp_upsert_peixe_imagem(
  'Hoplias_Malabaricus',
  'backend/data/train/Hoplias_Malabaricus/img01.jpg',
  'train', 1, 1, NULL, NULL, NULL, NULL, NULL
);

CALL sp_upsert_peixe_imagem(
  'Hoplias_Malabaricus',
  'backend/data/test/Hoplias_Malabaricus/img02.jpg',
  'test',  2, 0, NULL, NULL, NULL, NULL, NULL
);
drop table staging_imagens;

ALTER TABLE usuarios
  ADD COLUMN cpf CHAR(11) NULL AFTER email,
  ADD UNIQUE KEY uq_usuarios_cpf (cpf);

