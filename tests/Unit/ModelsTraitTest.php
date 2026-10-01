<?php

namespace Tests\Unit;

use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\StockMovement;
use PHPUnit\Framework\TestCase;

/**
 * Regresión del bug de namespace: Payment importaba App\Traits\BelongsToCompanyTrait,
 * que no existe (el trait vive en App\Src\Traits), y cualquier uso del modelo
 * terminaba en un fatal error "Trait not found".
 */
class ModelsTraitTest extends TestCase
{
    public function test_payment_model_can_be_loaded_and_uses_the_company_trait(): void
    {
        $this->assertTrue(class_exists(Payment::class));

        $this->assertContains(
            \App\Src\Traits\BelongsToCompanyTrait::class,
            class_uses_recursive(Payment::class)
        );
    }

    public function test_payment_method_model_can_be_loaded(): void
    {
        $this->assertTrue(class_exists(PaymentMethod::class));
    }

    public function test_stock_movement_is_mass_assignable(): void
    {
        $model = new StockMovement;

        $this->assertTrue($model->isFillable('product_id'));
        $this->assertTrue($model->isFillable('stock_after_change'));
    }
}
