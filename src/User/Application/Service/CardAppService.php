<?php
declare(strict_types=1);

namespace RidiPay\User\Application\Service;

use Ramsey\Uuid\Uuid;
use RidiPay\Library\EntityManagerProvider;
use RidiPay\Library\Log\StdoutLogger;
use RidiPay\Pg\Application\Service\PgAppService;
use RidiPay\Pg\Domain\Exception\CardRegistrationException;
use RidiPay\Pg\Domain\Exception\UnsupportedPgException;
use RidiPay\Pg\Domain\Service\PgHandlerFactory;
use RidiPay\User\Application\Dto\PaymentMethodDto;
use RidiPay\User\Application\Dto\PaymentMethodDtoFactory;
use RidiPay\User\Domain\Exception\CardAlreadyExistsException;
use RidiPay\User\Domain\Exception\LeavedUserException;
use RidiPay\User\Domain\Exception\NotFoundUserException;
use RidiPay\User\Domain\Exception\UnregisteredPaymentMethodException;
use RidiPay\User\Domain\Repository\PaymentMethodRepository;
use RidiPay\User\Domain\Service\CardService;
use RidiPay\User\Domain\Service\UserActionHistoryService;

class CardAppService
{
    /**
     * @param int $u_idx
     * @param string $card_number 카드 번호 16자리
     * @param string $card_password 카드 비밀번호 앞 2자리
     * @param string $card_expiration_date 카드 유효 기한 (YYMM)
     * @param string $tax_id 개인: 생년월일(YYMMDD) / 법인: 사업자 등록 번호 10자리
     * @return PaymentMethodDto
     * @throws CardAlreadyExistsException
     * @throws CardRegistrationException
     * @throws LeavedUserException
     * @throws UnsupportedPgException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Throwable
     */
    public static function registerCard(
        int $u_idx,
        string $card_number,
        string $card_expiration_date,
        string $card_password,
        string $tax_id
    ): PaymentMethodDto {
        $pg = PgAppService::getActivePg();
        $pg_handler = PgHandlerFactory::create($pg->name);

        $em = EntityManagerProvider::getEntityManager();
        $em->beginTransaction();

        try {
            try {
                UserAppService::validateUser($u_idx);
            } catch (NotFoundUserException $e) {
                UserAppService::createUser($u_idx);
            }

            $payment_method = CardService::registerCard(
                $u_idx,
                $card_number,
                $card_expiration_date,
                $card_password,
                $tax_id,
                $pg->id,
                $pg_handler
            );
            UserActionHistoryService::logAddCard($u_idx);

            $em->commit();
        } catch (\Throwable $t) {
            $em->rollback();
            $em->close();

            $logger = new StdoutLogger(__METHOD__);
            $logger->error($t->getMessage());

            throw $t;
        }

        return PaymentMethodDtoFactory::create($payment_method);
    }

    /**
     * @param int $u_idx
     * @param string $payment_method_id
     * @throws LeavedUserException
     * @throws UnregisteredPaymentMethodException
     * @throws NotFoundUserException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Throwable
     */
    public static function deleteCard(int $u_idx, string $payment_method_id): void
    {
        UserAppService::validateUser($u_idx);

        $payment_method_repo = PaymentMethodRepository::getRepository();
        $payment_method = $payment_method_repo->findOneByUuid(Uuid::fromString($payment_method_id));
        if (is_null($payment_method)) {
            throw new UnregisteredPaymentMethodException();
        }

        $em = EntityManagerProvider::getEntityManager();
        $em->beginTransaction();

        try {
            $payment_method->delete();
            $payment_method_repo->save($payment_method);

            UserActionHistoryService::logDeleteCard($u_idx);
            // TODO: first-party 정기 결제 해지 요청

            $em->commit();
        } catch (\Throwable $t) {
            $em->rollback();
            $em->close();

            $logger = new StdoutLogger(__METHOD__);
            $logger->error($t->getMessage());

            throw $t;
        }
    }
}
