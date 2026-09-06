<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommissionSettlementDeduction extends Model
{
    protected $guarded = ['id'];
    protected $table = 'commission_settlement_deductions';

    const PENALTY = 'penalty';
    const CHARGEBACK = 'chargeback';
    const REFUND = 'refund';
    const ADJUSTMENT = 'adjustment';

    public static function types(): array
    {
        return [
            self::PENALTY => 'Penalty',
            self::CHARGEBACK => 'Chargeback',
            self::REFUND => 'Refund',
            self::ADJUSTMENT => 'Adjustment / Platform Fee',
        ];
    }

    /**
     * [settlement description]
     * @return [type] [description]
     */
    public function settlement()
    {
        return $this->belongsTo(\App\Models\CommissionSettlement::class, 'commission_settlement_id');
    }
}
