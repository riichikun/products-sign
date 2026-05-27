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

namespace BaksDev\Products\Sign\Repository\ProductSignByOrder;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Orders\Order\Entity\Event\OrderEvent;
use BaksDev\Orders\Order\Entity\Items\OrderProductItem;
use BaksDev\Orders\Order\Entity\Order;
use BaksDev\Orders\Order\Entity\Products\OrderProduct;
use BaksDev\Orders\Order\Entity\User\OrderUser;
use BaksDev\Orders\Order\Type\Id\OrderUid;
use BaksDev\Products\Category\Entity\Offers\CategoryProductOffers;
use BaksDev\Products\Category\Entity\Offers\Variation\CategoryProductVariation;
use BaksDev\Products\Category\Entity\Offers\Variation\Modification\CategoryProductModification;
use BaksDev\Products\Product\Entity\Event\ProductEvent;
use BaksDev\Products\Product\Entity\Offers\ProductOffer;
use BaksDev\Products\Product\Entity\Offers\Variation\Modification\ProductModification;
use BaksDev\Products\Product\Entity\Offers\Variation\ProductVariation;
use BaksDev\Products\Product\Entity\Product;
use BaksDev\Products\Product\Entity\Trans\ProductTrans;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Sign\Entity\Code\ProductSignCode;
use BaksDev\Products\Sign\Entity\Event\ProductSignEvent;
use BaksDev\Products\Sign\Entity\Invariable\ProductSignInvariable;
use BaksDev\Products\Sign\Entity\ProductSign;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus\ProductSignStatusDone;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus\ProductSignStatusProcess;
use BaksDev\Users\Profile\UserProfile\Entity\Event\UserProfileEvent;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Generator;
use InvalidArgumentException;

final class ProductSignByOrderRepository implements ProductSignByOrderInterface
{
    /** Фильтр по продукту */

    private ProductUid|false $product = false;

    private ProductOfferConst|false $offer = false;

    private ProductVariationConst|false $variation = false;

    private ProductModificationConst|false $modification = false;

    /** Фильтр по заказу */

    private OrderUid|false $order = false;

    private UserProfileUid|false $profile = false;

    private ProductSignStatus $status;

    private string|false $part = false;

    private bool $item = false;


    public function __construct(private readonly DBALQueryBuilder $DBALQueryBuilder)
    {
        /** По умолчанию возвращаем знаки со статусом Process «В резерве» */
        $this->status = new ProductSignStatus(ProductSignStatusProcess::class);
    }

    /** Фильтр по продукту */
    public function product(Product|ProductUid|string $product): self
    {
        if(is_string($product))
        {
            $product = new ProductUid($product);
        }

        if($product instanceof Product)
        {
            $product = $product->getId();
        }

        $this->product = $product;

        return $this;
    }

    public function offer(ProductOfferConst|string|null|false $offer): self
    {
        if(empty($offer))
        {
            $this->offer = false;
            return $this;
        }

        if(is_string($offer))
        {
            $offer = new ProductOfferConst($offer);
        }

        $this->offer = $offer;

        return $this;
    }

    public function variation(ProductVariationConst|string|null|false $variation): self
    {
        if(empty($variation))
        {
            $this->variation = false;
            return $this;
        }

        if(is_string($variation))
        {
            $variation = new ProductVariationConst($variation);
        }

        $this->variation = $variation;

        return $this;
    }

    public function modification(ProductModificationConst|string|null|false $modification): self
    {
        if(empty($modification))
        {
            $this->modification = false;
            return $this;
        }

        if(is_string($modification))
        {
            $modification = new ProductModificationConst($modification);
        }

        $this->modification = $modification;

        return $this;
    }

    public function profile(UserProfileUid|string|UserProfile $profile): self
    {
        if(is_string($profile))
        {
            $profile = new UserProfileUid($profile);
        }

        if($profile instanceof UserProfile)
        {
            $profile = $profile->getId();
        }

        $this->profile = $profile;

        return $this;

    }

    public function forPart(string $part): self
    {
        $this->part = $part;

        return $this;
    }

    public function forOrder(Order|OrderUid|string $order): self
    {
        if($order instanceof Order)
        {
            $order = $order->getId();
        }

        if(is_string($order))
        {
            $order = new OrderUid($order);
        }

        $this->order = $order;

        return $this;
    }

    /**
     * Возвращает знаки со статусом Done «Выполнен»
     */
    public function withStatusDone(): self
    {
        $this->status = new ProductSignStatus(ProductSignStatusDone::class);
        return $this;
    }

    /** Честные знаки, у которых нет связи с единицей продукта */
    public function withoutItem(): self
    {
        $this->item = true;
        return $this;
    }

    /**
     * Метод возвращает все штрихкоды «Честный знак» для печати по идентификатору заказа
     * По умолчанию возвращает знаки со статусом Process «В резерве»
     *
     * @return Generator<int, ProductSignByOrderResult>|false
     */
    public function findAll(): Generator|false
    {
        if($this->order === false)
        {
            throw new InvalidArgumentException('Не передан обязательный параметр order через вызов метода ->forOrder(...)');
        }

        $dbal = $this->DBALQueryBuilder
            ->createQueryBuilder(self::class)
            ->bindLocal();

        $dbal
            ->addSelect('event.comment')
            ->addSelect('event.product')
            ->from(
                ProductSignEvent::class,
                'event',
            );

        $dbal
            ->where('event.ord = :ord')
            ->setParameter('ord', $this->order, OrderUid::TYPE);

        $dbal
            ->andWhere('event.status = :status')
            ->setParameter('status', $this->status, ProductSignStatus::TYPE);


        $dbal->leftJoin(
            'event',
            Order::class,
            'ord',
            'ord.id = event.ord',
        );

        $dbal->join(
            "ord",
            OrderProduct::class,
            "orders_product",
            "orders_product.event = ord.event",
        );

        $dbal->join(
            "orders_product",
            ProductEvent::class,
            "product_event",
            "product_event.id = orders_product.product",
        );

        $dbal
            ->addSelect("product_offer.value AS product_offer_value")
            ->addSelect('product_offer.postfix as product_offer_postfix')
            ->leftJoin(
                "orders_product",
                ProductOffer::class,
                "product_offer",
                "product_offer.id = orders_product.offer",
            );


        /** Получаем тип торгового предложения */
        $dbal
            ->addSelect('category_offer.reference as product_offer_reference')
            ->leftJoin(
                'product_offer',
                CategoryProductOffers::class,
                'category_offer',
                'category_offer.id = product_offer.category_offer',
            );


        $dbal
            ->addSelect("product_variation.value AS product_variation_value")
            ->addSelect('product_variation.postfix as product_variation_postfix')
            ->leftJoin(
                "orders_product",
                ProductVariation::class,
                "product_variation",
                "product_variation.id = orders_product.variation",
            );


        /** Получаем тип множественного варианта */
        $dbal
            ->addSelect('category_offer_variation.reference as product_variation_reference')
            ->leftJoin(
                'product_variation',
                CategoryProductVariation::class,
                'category_offer_variation',
                'category_offer_variation.id = product_variation.category_variation',
            );


        $dbal
            ->addSelect("product_modification.value AS product_modification_value")
            ->addSelect('product_modification.postfix as product_modification_postfix')
            ->leftJoin(
                "orders_product",
                ProductModification::class,
                "product_modification",
                "product_modification.id = orders_product.modification",
            );


        /** Получаем тип модификации */
        $dbal
            ->addSelect('category_offer_modification.reference as product_modification_reference')
            ->leftJoin(
                'product_modification',
                CategoryProductModification::class,
                'category_offer_modification',
                'category_offer_modification.id = product_modification.category_modification',
            );


        $dbal
            ->addSelect("product_trans.name AS product_name")
            ->leftJoin(
                "orders_product",
                ProductTrans::class,
                "product_trans",
                "product_trans.event = product_event.id AND product_trans.local = :local",
            );

        if($this->profile !== false)
        {
            $dbal->leftJoin(
                'ord',
                OrderUser::class,
                'ord_usr',
                'ord_usr.event = ord.event',
            );

            $dbal
                ->join(
                    'ord_usr',
                    UserProfileEvent::class,
                    'profile_event',
                    'profile_event.id = ord_usr.profile AND profile_event.profile = :profile',
                )
                ->setParameter('profile', $this->profile, UserProfileUid::TYPE);
        }

        $dbal
            ->addSelect('main.id AS sign_id')
            ->addSelect('main.event AS sign_event')
            ->join(
                'event',
                ProductSign::class,
                'main',
                'main.id = event.main',
            );


        if($this->product)
        {
            $offerParam = $this->offer ? ' = :offer' : ' IS NULL';
            !$this->offer ?: $dbal->setParameter('offer', $this->offer, ProductOfferConst::TYPE);

            $variationParam = $this->variation ? ' = :variation' : ' IS NULL';
            !$this->variation ?: $dbal->setParameter('variation', $this->variation, ProductVariationConst::TYPE);

            $modificationParam = $this->modification ? ' = :modification' : ' IS NULL';
            !$this->modification ?: $dbal->setParameter('modification', $this->modification, ProductModificationConst::TYPE);

            $dbal
                ->join(
                    'event',
                    ProductSignInvariable::class,
                    'invariable',
                    '
                    invariable.main = main.id AND 
                    invariable.product = :product AND
                    invariable.offer '.$offerParam.' AND
                    invariable.variation '.$variationParam.' AND
                    invariable.modification '.$modificationParam.'
                '.($this->part ? ' AND invariable.part = :part' : ''),
                )
                ->setParameter(
                    key: 'product',
                    value: $this->product,
                    type: ProductUid::TYPE,
                );

            if($this->part)
            {
                $dbal->setParameter(
                    key: 'part',
                    value: $this->part,
                );
            }

        }

        $dbal
            ->addSelect(
                "
                CASE
                   WHEN code.name IS NOT NULL 
                   THEN CONCAT ( '/upload/barcode', '/', code.name)
                   ELSE NULL
                END AS code_image
            ",
            )
            ->addSelect("code.ext AS code_ext")
            ->addSelect("code.cdn AS code_cdn")
            ->addSelect("code.code AS code_string")
            ->leftJoin(
                'event',
                ProductSignCode::class,
                'code',
                'code.main = main.id',
            );

        /** Есть ли на единицу продукции Честный знак */
        if(true === $this->item)
        {
            /** Получаем все item по номеру заказа */
            $item = $this->DBALQueryBuilder->createQueryBuilder(self::class);

            $item
                ->select('1')
                ->from(Order::class, 'ord');

            /** Событие */
            $item
                ->join(
                    'ord',
                    OrderEvent::class,
                    'orders_event',
                    '
                        orders_event.id = ord.event AND
                        orders_event.orders = :ord
                        ',
                );

            $item
                ->setParameter(
                    'ord',
                    $this->order,
                    OrderUid::TYPE,
                );


            /** Продукт */
            $item
                ->join(
                    'orders_event',
                    OrderProduct::class,
                    'orders_product',
                    'orders_product.event = orders_event.id',
                );

            /** Единицы */
            $item
                ->join(
                    'orders_product',
                    OrderProductItem::class,
                    'orders_product_item',
                    'orders_product_item.product = orders_product.id',
                );

            /** Связь item с ЧЗ */
            $item->where('orders_product_item.const = event.product');

            /** Проверяем, что у ЧЗ нет связи с item */
            $dbal->andWhere('NOT EXISTS('.$item->getSQL().')');
        }

        $result = $dbal->fetchAllHydrate(ProductSignByOrderResult::class);

        /** Сбрасываем фильтры */
        $this->product = false;
        $this->offer = false;
        $this->variation = false;
        $this->modification = false;
        $this->order = false;
        $this->profile = false;
        $this->part = false;
        $this->item = false;
        $this->status = new ProductSignStatus(ProductSignStatusProcess::class);

        return $result->valid() ? $result : false;
    }
}