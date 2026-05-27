<?php
/*
 *  Copyright 2025.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Products\Sign\Controller\Admin\Documents\Orders;

use BaksDev\Barcode\Writer\BarcodeFormat;
use BaksDev\Barcode\Writer\BarcodeType;
use BaksDev\Barcode\Writer\BarcodeWrite;
use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Core\Twig\CallTwigFuncExtension;
use BaksDev\Core\Type\UidType\ParamConverter;
use BaksDev\Orders\Order\Type\Id\OrderUid;
use BaksDev\Products\Sign\Repository\ProductSignByOrder\ProductSignByOrderInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Generator;
use Twig\Environment;

#[AsController]
#[RoleSecurity(['ROLE_USER'])]
final class PrintController extends AbstractController
{
    #[Route(
        path: '/document/product/sign/document/print/orders/{order}',
        name: 'document.print.orders',
        methods: ['GET'])
    ]
    public function orders(
        ProductSignByOrderInterface $productSignByOrder,
        #[ParamConverter(OrderUid::class)] $order,
        BarcodeWrite $BarcodeWrite,
        #[Target('productsSignLogger')] LoggerInterface $Logger,
        Environment $environment
    ): Response
    {
        $codes = $productSignByOrder
            ->forOrder($order)
            ->findAll();

        $codesGenerated = [];

        if($codes instanceof Generator)
        {
            foreach($codes as $ProductSignByOrderResult)
            {
                $isRenderBarcode = $BarcodeWrite
                    ->text($ProductSignByOrderResult->getBigCode())
                    ->type(BarcodeType::DataMatrix)
                    ->format(BarcodeFormat::SVG)
                    ->generate(filename: (string) $ProductSignByOrderResult->getSignId());

                if(false === $isRenderBarcode)
                {
                    $Logger->critical(
                        sprintf('products-sign: ошибка генерации честного знака заказа %s', $order->number),
                        [self::class.':'.__LINE__, 'ProductSignEventUid' => $ProductSignByOrderResult->getSignId()],
                    );

                    continue;
                }

                $render = $BarcodeWrite->render();
                $render = strip_tags($render, ['path']);
                $render = trim($render);

                $BarcodeWrite->remove(); // удаляем после чтения


                /** Получаем код для отображения на странице с честным знаком */
                $code = $ProductSignByOrderResult->getSmallCodeNoGTIN();


                /** Формируем имя продукта для отображения на странице с честным знаком */

                $call = $environment->getExtension(CallTwigFuncExtension::class);

                $name = trim($ProductSignByOrderResult->getProductName());

                $strOffer = $name;


                /**
                 * Множественный вариант
                 */

                $variation = $call->call(
                    $environment,
                    $ProductSignByOrderResult->getProductVariationValue(),
                    $ProductSignByOrderResult->getProductVariationReference().'_render',
                );

                $strOffer .= $variation ? ' '.trim($variation) : '';


                /**
                 * Модификация множественного варианта
                 */

                $modification = $call->call(
                    $environment,
                    $ProductSignByOrderResult->getProductModificationValue(),
                    $ProductSignByOrderResult->getProductModificationReference().'_render',
                );

                $strOffer .= $modification ? ' '.trim($modification) : '';


                /**
                 * Торговое предложение
                 */

                $offer = $call->call(
                    $environment,
                    $ProductSignByOrderResult->getProductOfferValue(),
                    $ProductSignByOrderResult->getProductOfferReference().'_render',
                );

                $strOffer .= $offer ? ' '.trim($offer) : '';

                $strOffer .= $ProductSignByOrderResult->getProductOfferPostfix() ? ' '.$ProductSignByOrderResult->getProductOfferPostfix() : '';
                $strOffer .= $ProductSignByOrderResult->getProductVariationPostfix() ? ' '.$ProductSignByOrderResult->getProductVariationPostfix() : '';
                $strOffer .= $ProductSignByOrderResult->getProductModificationPostfix() ? ' '.$ProductSignByOrderResult->getProductModificationPostfix() : '';


                $codesGenerated[] = ['image' => $render, 'code' => $code, 'name' => $strOffer];
            }
        }

        return $this->render(
            ['codes' => $codesGenerated],
            dir: 'admin.print',
        );
    }
}
