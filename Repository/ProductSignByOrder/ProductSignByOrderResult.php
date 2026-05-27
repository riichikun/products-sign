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
 *
 */

declare(strict_types=1);

namespace BaksDev\Products\Sign\Repository\ProductSignByOrder;

use BaksDev\Orders\Order\Type\Items\Const\OrderProductItemConst;
use BaksDev\Products\Sign\Type\Event\ProductSignEventUid;
use BaksDev\Products\Sign\Type\Id\ProductSignUid;

/** @see ProductSignByOrderResult */
final readonly class ProductSignByOrderResult
{

    public function __construct(
        private string $sign_id, // " => "0195396e-7f26-742d-8fbf-a9ed36a2d029"
        private string $sign_event, // " => "0195b796-9b6c-7654-a557-774d4bede31a"

        private string $code_string,
        // " => "(01)04603766681672(21)5t<!--Za6ucZhTb(91)EE10(92)ej/Cgv8P4Yf7cLJC4Jaf5lc+BEcL6pae05tsT2+TiMc="-->

        private string $code_image, // " => "/upload/product_sign_code/0a58f0ee235e31dc73b5551299a4af88"
        private string $code_ext, // " => "webp"
        private bool $code_cdn, // " => true
        private ?string $comment,

        private ?string $product = null,

        private ?string $product_name,

        private ?string $product_offer_value,
        private ?string $product_offer_reference,
        private ?string $product_offer_postfix,

        private ?string $product_variation_value,
        private ?string $product_variation_reference,
        private ?string $product_variation_postfix,

        private ?string $product_modification_value,
        private ?string $product_modification_reference,
        private ?string $product_modification_postfix,
    ) {}

    public function getSignId(): ProductSignUid
    {
        return new ProductSignUid($this->sign_id);
    }

    public function getSignEvent(): ProductSignEventUid
    {
        return new ProductSignEventUid($this->sign_event);
    }

    public function getSmallCode(): string
    {
        preg_match('/^(.*?)\(\d{2}\).{4}\(\d{2}\)/', $this->code_string, $matches);


        if(isset($matches[1]))
        {

            // Преобразуем строку в массив символов
            $chars = str_split($matches[1]);

            // 1 символ (индекс 0)
            if($chars[0] === '(')
            {
                unset($chars[0]);
            }

            // 4 символ (индекс 3)
            if($chars[3] === ')')
            {
                unset($chars[3]);
            }


            // 19 символ (индекс 18)
            if($chars[18] === '(')
            {
                unset($chars[18]);
            }

            // 22 символ (индекс 21)
            if($chars[21] === ')')
            {
                unset($chars[21]);
            }

            return implode('', $chars);
        }

        return $this->code_string;
    }

    public function getSmallCodeNoGTIN(): string
    {
        preg_match('/\\(\\d+\\).*?\\(\\d+\\)(.*?)\\(\\d+\\)/', $this->code_string, $matches);

        if(isset($matches[1]))
        {
            return $matches[1];
        }

        return $this->code_string;
    }

    public function getBigCode(): string
    {
        $subChar = "";
        preg_match_all('/\((\d{2})\)((?:(?!\(\d{2}\)).)*)/', $this->code_string, $matches, PREG_SET_ORDER);
        return $matches[0][1].$matches[0][2].$matches[1][1].$matches[1][2].$subChar.$matches[2][1].$matches[2][2].$subChar.$matches[3][1].$matches[3][2];
    }

    public function getCodeImage(): string
    {
        return $this->code_image;
    }

    public function getCodeExt(): string
    {
        return $this->code_ext;
    }

    public function isCodeCdn(): bool
    {
        return $this->code_cdn === true;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function getProduct(): ?OrderProductItemConst
    {
        return true === empty($this->product) ? null : new OrderProductItemConst($this->product);
    }

    public function getProductName(): ?string
    {
        return $this->product_name;
    }

    /** Offer */

    public function getProductOfferValue(): ?string
    {
        return $this->product_offer_value;
    }

    public function getProductOfferPostfix(): ?string
    {
        return $this->product_offer_postfix;
    }

    public function getProductOfferReference(): ?string
    {
        return $this->product_offer_reference;
    }


    /** Variation */

    public function getProductVariationValue(): ?string
    {
        return $this->product_variation_value;
    }

    public function getProductVariationPostfix(): ?string
    {
        return $this->product_variation_postfix;
    }

    public function getProductVariationReference(): ?string
    {
        return $this->product_variation_reference;
    }


    /** Modification */

    public function getProductModificationValue(): ?string
    {
        return $this->product_modification_value;
    }

    public function getProductModificationPostfix(): ?string
    {
        return $this->product_modification_postfix;
    }

    public function getProductModificationReference(): ?string
    {
        return $this->product_modification_reference;
    }
}