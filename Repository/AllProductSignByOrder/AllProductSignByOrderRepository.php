<?php
/*
 *  Copyright 2026.  Baks.dev <admin@baks.dev>
 *  
 *  Permission is hereby granted, free of charge, to any person obtaining a copy
 *  of this software and associated documentation files (the "Software"), to deal
 *  in the Software without restriction, including without limitation the rights
 *  to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 *  copies of the Software, and to permit persons to whom the Software is furnished
 *  to do so, subject to the following conditions:
 *  
 *  The above copyright notice and this permission notice shall be included in all
 *  copies or substantial portions of the Software.
 *  
 *  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 *  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 *  FITNESS FOR A PARTICULAR PURPOSE AND NON INFRINGEMENT. IN NO EVENT SHALL THE
 *  AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 *  LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 *  OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 *  THE SOFTWARE.
 */

declare(strict_types=1);

namespace BaksDev\Products\Sign\Repository\AllProductSignByOrder;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Orders\Order\Entity\Items\OrderProductItem;
use BaksDev\Orders\Order\Entity\Order;
use BaksDev\Orders\Order\Entity\Products\OrderProduct;
use BaksDev\Orders\Order\Type\Id\OrderUid;
use BaksDev\Products\Sign\Entity\Code\ProductSignCode;
use BaksDev\Products\Sign\Entity\Event\ProductSignEvent;
use BaksDev\Products\Sign\Entity\Invariable\ProductSignInvariable;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus\Collection\ProductSignStatusInterface;
use Doctrine\DBAL\ArrayParameterType;
use InvalidArgumentException;

final class AllProductSignByOrderRepository implements AllProductSignByOrderInterface
{
    private OrderUid|false $order = false;

    private array|null $statuses = null;

    public function __construct(
        private readonly DBALQueryBuilder $DBALQueryBuilder
    ) {}

    /** ЧЗ, связанные с продуктом из заказа */
    public function forOrder(Order|OrderUid $order): self
    {
        if($order instanceof Order)
        {
            $order = $order->getId();
        }

        $this->order = $order;
        return $this;
    }

    /** Фильтр по статусу ЧЗ */
    public function forStatus(ProductSignStatus|ProductSignStatusInterface|string $status): self
    {
        if(is_string($status) || $status instanceof ProductSignStatusInterface)
        {
            $status = new ProductSignStatus($status);
        }

        $this->statuses[] = $status;
        return $this;
    }

    /**
     * Информация о Честном знаке
     */
    public function findAll(): array|false
    {
        if(false === ($this->order instanceof OrderUid))
        {
            throw new InvalidArgumentException('Не передан обязательный параметр запроса order');
        }

        $dbal = $this->DBALQueryBuilder->createQueryBuilder(self::class);

        $dbal->from(Order::class, 'ord');

        $dbal
            ->where('ord.id = :ord')
            ->setParameter('ord', $this->order, OrderUid::TYPE);


        /** Продукты в заказе */
        $dbal
            ->join(
                'ord',
                OrderProduct::class,
                'orders_product',
                'orders_product.event = ord.event',
            );


        /** Единицы продукции */
        $dbal
            ->select('orders_product_item.const AS const')
            ->join(
                'ord',
                OrderProductItem::class,
                'orders_product_item',
                'orders_product_item.product = orders_product.id',
            );

        /** ЧЗ с заказом и единицей продукции */
        $dbal
            ->addSelect('product_sign_event.status AS status')
            ->addSelect('product_sign_event.comment AS comment')
            ->join(
                'ord',
                ProductSignEvent::class,
                'product_sign_event',
                '
                    product_sign_event.ord = ord.id 
                    AND product_sign_event.product = orders_product_item.const
                '.(false === empty($this->statuses) ? ' AND product_sign_event.status IN (:statuses)' : ''),
            );


        if(false === empty($this->statuses))
        {
            $dbal->setParameter(
                'statuses',
                $this->statuses,
                ArrayParameterType::STRING,
            );
        }

        //
        //        $dbal
        //            ->join(
        //                'product_sign_event',
        //                ProductSign::class,
        //                'product_sign',
        //                'product_sign.event = product_sign_event.id',
        //            );


        $dbal
            ->addSelect('product_sign_invariable.number AS number')
            ->leftJoin(
                'product_sign_event',
                ProductSignInvariable::class,
                'product_sign_invariable',
                'product_sign_invariable.main = product_sign_event.main',
            );

        /** Кодировка ЧЗ */
        $dbal
            ->addSelect('product_sign_code.code AS code')
            ->leftJoin(
                'product_sign_event',
                ProductSignCode::class,
                'product_sign_code',
                'product_sign_code.main = product_sign_event.main',
            );

        $this->order = false;
        $this->statuses = null;

        return $dbal->fetchAllIndexHydrate(AllProductSignByOrderResult::class);
    }
}
