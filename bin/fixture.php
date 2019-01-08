<?php

require __DIR__.'/../vendor/autoload.php';

use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Tools\SchemaTool;
use Ramsey\Uuid\Doctrine\UuidBinaryType;
use RidiPay\Kernel;
use RidiPay\Library\EntityManagerProvider;
use RidiPay\Library\Pg\Kcp\Company;
use RidiPay\Pg\Domain\Entity\PgEntity;
use RidiPay\User\Domain\Entity\CardIssuerEntity;
use Symfony\Component\Dotenv\Dotenv;

Type::addType('uuid_binary', UuidBinaryType::class);

if (!Kernel::isLocal()) {
    throw new \RuntimeException('This file can be executed only in local environments');
}

$dotenv_file_path = __DIR__ . '/../.env';
if (!file_exists($dotenv_file_path)) {
    throw new \RuntimeException("A .env file doesn't exist.");
}
(new Dotenv())->load($dotenv_file_path);

// Table 생성
$em = EntityManagerProvider::getEntityManager();
$schema_tool = new SchemaTool($em);
$schema_tool->createSchema($em->getMetadataFactory()->getAllMetadata());

// Pg Fixture 생성
$pg = PgEntity::createKcp();
$em->persist($pg);
$em->flush($pg);

// Partner Fixture 생성
$sql = "INSERT INTO `partner` (`name`, `password`, `api_key`, `secret_key`, `is_valid`, `is_first_party`)
    VALUES ('ridibooks', ?, ?, ?, 1, 1);";
$partner = $em->getConnection()->executeQuery(
    $sql,
    [
        password_hash('local', PASSWORD_DEFAULT),
        hex2bin('2154A6EBA9A0480DAE8C166281D7D90F'),
        'jY2DpJJyQqi3eAnODcKJnfM6Go6TpIOzD4BGvuDx/R2FuUODq0cGJNrn8smNsFcumtrge4RFYT9cZ52nJTlV3z/i6WIfYT99'
    ]
);

// CardIssuer Fixture 생성
$card_issuers = [
    new CardIssuerEntity(
        $pg->getId(),
        Company::KOOKMIN,
        Company::getKoreanName(Company::KOOKMIN),
        '#766c60',
        'https://pay.ridibooks.com/public/images/card_logo/logo_cckm.png'
    ),
    new CardIssuerEntity(
        $pg->getId(),
        Company::NONGHYUP,
        Company::getKoreanName(Company::NONGHYUP),
        '#02469b',
        'https://pay.ridibooks.com/public/images/card_logo/logo_ccnh.png'
    ),
    new CardIssuerEntity(
        $pg->getId(),
        Company::SHINSEGAE_HANMI,
        Company::getKoreanName(Company::SHINSEGAE_HANMI),
        '#68737a',
        'https://pay.ridibooks.com/public/images/card_logo/logo_ccsg.png'
    ),
    new CardIssuerEntity(
        $pg->getId(),
        Company::CITI,
        Company::getKoreanName(Company::CITI),
        '#0057a0',
        'https://pay.ridibooks.com/public/images/card_logo/logo_ccct.png'
    ),
    new CardIssuerEntity(
        $pg->getId(),
        Company::HANMI,
        Company::getKoreanName(Company::HANMI),
        '#0057a0',
        'https://pay.ridibooks.com/public/images/card_logo/logo_cchm.png'
    ),
    new CardIssuerEntity(
        $pg->getId(),
        Company::VISA,
        Company::getKoreanName(Company::VISA),
        '#192269',
        'https://pay.ridibooks.com/public/images/card_logo/logo_cvsf.png'
    ),
    new CardIssuerEntity(
        $pg->getId(),
        Company::LOTTE_AMEX,
        Company::getKoreanName(Company::LOTTE_AMEX),
        '#c4000d',
        'https://pay.ridibooks.com/public/images/card_logo/logo_ccam.png'
    ),
    new CardIssuerEntity(
        $pg->getId(),
        Company::LOTTE,
        Company::getKoreanName(Company::LOTTE),
        '#c4000d',
        'https://pay.ridibooks.com/public/images/card_logo/logo_cclo.png'
    ),
    new CardIssuerEntity(
        $pg->getId(),
        Company::BC,
        Company::getKoreanName(Company::BC),
        '#c4000d',
        'https://pay.ridibooks.com/public/images/card_logo/logo_ccbc.png'
    ),
    new CardIssuerEntity(
        $pg->getId(),
        Company::HANA_SK,
        Company::getKoreanName(Company::HANA_SK),
        '#008275',
        'https://pay.ridibooks.com/public/images/card_logo/logo_cchn.png'
    ),
    new CardIssuerEntity(
        $pg->getId(),
        Company::SAMSUNG,
        Company::getKoreanName(Company::SAMSUNG),
        '#101010',
        'https://pay.ridibooks.com/public/images/card_logo/logo_ccss.png'
    ),
    new CardIssuerEntity(
        $pg->getId(),
        Company::GWANGJU,
        Company::getKoreanName(Company::GWANGJU),
        '#012d6b',
        'https://pay.ridibooks.com/public/images/card_logo/logo_cckj.png'
    ),
    new CardIssuerEntity(
        $pg->getId(),
        Company::SUHYUP,
        Company::getKoreanName(Company::SUHYUP),
        '#0083cb',
        'https://pay.ridibooks.com/public/images/card_logo/logo_ccsu.png'
    ),
    new CardIssuerEntity(
        $pg->getId(),
        Company::JEONBUK,
        Company::getKoreanName(Company::JEONBUK),
        '#012e85',
        'https://pay.ridibooks.com/public/images/card_logo/logo_ccjb.png'
    ),
    new CardIssuerEntity(
        $pg->getId(),
        Company::JEJU,
        Company::getKoreanName(Company::JEJU),
        '#0083cb',
        'https://pay.ridibooks.com/public/images/card_logo/logo_cccj.png'
    ),
    new CardIssuerEntity(
        $pg->getId(),
        Company::SHINHAN,
        Company::getKoreanName(Company::SHINHAN),
        '#131741',
        'https://pay.ridibooks.com/public/images/card_logo/logo_cclg.png'
    ),
    new CardIssuerEntity(
        $pg->getId(),
        Company::MASTER,
        Company::getKoreanName(Company::MASTER),
        '#ffc841',
        'https://pay.ridibooks.com/public/images/card_logo/logo_cmcf.png'
    ),
    new CardIssuerEntity(
        $pg->getId(),
        Company::JCB,
        Company::getKoreanName(Company::JCB),
        '#1b78d1',
        'https://pay.ridibooks.com/public/images/card_logo/logo_cjcf.png'
    ),
    new CardIssuerEntity(
        $pg->getId(),
        Company::KOREA_EXCHANGE,
        Company::getKoreanName(Company::KOREA_EXCHANGE),
        '#008275',
        'https://pay.ridibooks.com/public/images/card_logo/logo_ccke.png'
    ),
    new CardIssuerEntity(
        $pg->getId(),
        Company::HYUNDAI,
        Company::getKoreanName(Company::HYUNDAI),
        '#191919',
        'https://pay.ridibooks.com/public/images/card_logo/logo_ccdi.png'
    ),
    new CardIssuerEntity(
        $pg->getId(),
        Company::UNION,
        Company::getKoreanName(Company::UNION),
        '#454545',
        'https://pay.ridibooks.com/public/images/card_logo/logo_ccuf.png'
    )
];
foreach ($card_issuers as $card_issuer) {
    $em->persist($card_issuer);
    $em->flush($card_issuer);
}
