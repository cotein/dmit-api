<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class AccountingAccountTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('accounting_accounts')->truncate();

        DB::table('accounting_accounts')->insert([
            [
                'account' => 1000000,
                'imputable' => 'N',
                'name' => 'ACTIVO',
                'parent_account' => 0,
            ],
            [
                'account' => 2000000,
                'imputable' => 'N',
                'name' => 'PASIVO',
                'parent_account' => 0,
            ],
            [
                'account' => 3000000,
                'imputable' => 'N',
                'name' => 'PATRIMONIO NETO',
                'parent_account' => 0,
            ],
            [
                'account' => 4000000,
                'imputable' => 'N',
                'name' => 'RESULTADO POSITIVO',
                'parent_account' => 0,
            ],
            [
                'account' => 5000000,
                'imputable' => 'N',
                'name' => 'RESULTADO NEGATIVO',
                'parent_account' => 0,
            ],
            [
                'account' => 1001000,
                'imputable' => 'N',
                'name' => 'CAJA',
                'parent_account' => 1,
            ],
            [
                'account' => 1002000,
                'imputable' => 'N',
                'name' => 'FONDO FIJO',
                'parent_account' => 1,
            ],
            [
                'account' => 1003000,
                'imputable' => 'N',
                'name' => 'BANCO XXX C/C',
                'parent_account' => 1,
            ],
            [
                'account' => 1004000,
                'imputable' => 'N',
                'name' => 'BANCO XXX C/A',
                'parent_account' => 1,
            ],
            [
                'account' => 1005000,
                'imputable' => 'N',
                'name' => 'VALORES A DEPOSITAR',
                'parent_account' => 1,
            ],
            [
                'account' => 1006000,
                'imputable' => 'N',
                'name' => 'VALORES DIFERIDOS A DEPOSITAR',
                'parent_account' => 1,
            ],
            [
                'account' => 1007000,
                'imputable' => 'N',
                'name' => 'CHEQUES RECHAZADOS',
                'parent_account' => 1,
            ],
            [
                'account' => 1008000,
                'imputable' => 'N',
                'name' => 'MONEDA EXTRANJERA',
                'parent_account' => 1,
            ],
            [
                'account' => 1008100,
                'imputable' => 'N',
                'name' => 'DOLARES',
                'parent_account' => 13,
            ],
            [
                'account' => 1008200,
                'imputable' => 'N',
                'name' => 'REALES',
                'parent_account' => 13,
            ],
            [
                'account' => 1009000,
                'imputable' => 'N',
                'name' => 'VENTAS',
                'parent_account' => 1,
            ],
            [
                'account' => 1010000,
                'imputable' => 'N',
                'name' => 'MUEBLES Y ÚTILES',
                'parent_account' => 1,
            ],
            [
                'account' => 1011000,
                'imputable' => 'N',
                'name' => 'INSTALACIONES',
                'parent_account' => 1,
            ],
            [
                'account' => 1012000,
                'imputable' => 'N',
                'name' => 'INMUEBLES',
                'parent_account' => 1,
            ],
            [
                'account' => 1013000,
                'imputable' => 'N',
                'name' => 'RODADOS',
                'parent_account' => 1,
            ],
            [
                'account' => 1014000,
                'imputable' => 'N',
                'name' => 'MAQUINARIAS',
                'parent_account' => 1,
            ],
            [
                'account' => 1015000,
                'imputable' => 'N',
                'name' => 'EQUIPOS DE COMPUTACIÓN',
                'parent_account' => 1,
            ],
            [
                'account' => 1016000,
                'imputable' => 'N',
                'name' => 'DEUDORES',
                'parent_account' => 1,
            ],
            [
                'account' => 1017000,
                'imputable' => 'N',
                'name' => 'DEUDORES POR VENTAS',
                'parent_account' => 23,
            ],
            [
                'account' => 1018000,
                'imputable' => 'N',
                'name' => 'DEUDORES VARIOS',
                'parent_account' => 23,
            ],
            [
                'account' => 1019000,
                'imputable' => 'N',
                'name' => 'DEUDORES MOROSOS',
                'parent_account' => 23,
            ],
            [
                'account' => 1020000,
                'imputable' => 'N',
                'name' => 'DEUDORES EN GESTIÓN JUDICIAL',
                'parent_account' => 23,
            ],
            [
                'account' => 1021000,
                'imputable' => 'N',
                'name' => 'DOCUMENTOS A COBRAR',
                'parent_account' => 23,
            ],
            [
                'account' => 1022000,
                'imputable' => 'N',
                'name' => 'IVA CRÉDITO FISCAL',
                'parent_account' => 1,
            ],
            [
                'account' => 1022100,
                'imputable' => 'N',
                'name' => 'IVA CRÉDITO FISCAL 21%',
                'parent_account' => 29,
            ],
            [
                'account' => 1022200,
                'imputable' => 'N',
                'name' => 'IVA CRÉDITO FISCAL 10,5%',
                'parent_account' => 29,
            ],
            [
                'account' => 1023000,
                'imputable' => 'N',
                'name' => 'IVA A FAVOR',
                'parent_account' => 1,
            ],
            [
                'account' => 2001000,
                'imputable' => 'N',
                'name' => 'DOCUMENTOS A PAGAR',
                'parent_account' => 2,
            ],
            [
                'account' => 2002000,
                'imputable' => 'N',
                'name' => 'PROVEEDORES',
                'parent_account' => 2,
            ],
            [
                'account' => 2003000,
                'imputable' => 'N',
                'name' => 'ACREEDORES VARIOS',
                'parent_account' => 2,
            ],
            [
                'account' => 2004000,
                'imputable' => 'N',
                'name' => 'CHEQUES A PAGAR  ',
                'parent_account' => 2,
            ],
            [
                'account' => 2005000,
                'imputable' => 'N',
                'name' => 'IVA DÉBITO FISCAL',
                'parent_account' => 2,
            ],
            [
                'account' => 2006000,
                'imputable' => 'N',
                'name' => 'IVA A PAGAR',
                'parent_account' => 2,
            ],
            [
                'account' => 3001000,
                'imputable' => 'N',
                'name' => 'CAPITAL ',
                'parent_account' => 3,
            ],
            [
                'account' => 3002000,
                'imputable' => 'N',
                'name' => 'RESULTADO DEL EJERCICIO',
                'parent_account' => 3,
            ],
            [
                'account' => 3003000,
                'imputable' => 'N',
                'name' => 'RESERVA LEGAL',
                'parent_account' => 3,
            ],
            [
                'account' => 3004000,
                'imputable' => 'N',
                'name' => 'RESERVA FACULTATIVA',
                'parent_account' => 3,
            ],
            [
                'account' => 3005000,
                'imputable' => 'N',
                'name' => 'RESERVA ESTATUTARIA',
                'parent_account' => 3,
            ],
            [
                'account' => 4001000,
                'imputable' => 'N',
                'name' => 'VENTAS',
                'parent_account' => 4,
            ],
            [
                'account' => 4002000,
                'imputable' => 'N',
                'name' => 'COMISIONES COBRADAS',
                'parent_account' => 4,
            ],
            [
                'account' => 4003000,
                'imputable' => 'N',
                'name' => 'ALQUILERES COBRADOS',
                'parent_account' => 4,
            ],
            [
                'account' => 4004000,
                'imputable' => 'N',
                'name' => 'INTERESES COBRADOS',
                'parent_account' => 4,
            ],
            [
                'account' => 4005000,
                'imputable' => 'N',
                'name' => 'DESCUENTOS OBTENIDOS',
                'parent_account' => 4,
            ],
            [
                'account' => 4006000,
                'imputable' => 'N',
                'name' => 'DIFERENICIA POSITIVA DE CAMBIO',
                'parent_account' => 4,
            ],
            [
                'account' => 4007000,
                'imputable' => 'N',
                'name' => 'SOBRANTE DE CAJA',
                'parent_account' => 4,
            ],
            [
                'account' => 4008000,
                'imputable' => 'N',
                'name' => 'SOBRANTE DE MERCADERÍAS',
                'parent_account' => 4,
            ],
            [
                'account' => 5001000,
                'imputable' => 'N',
                'name' => 'COSTO DE MERCADERIAS VENDIDAS (CMV)',
                'parent_account' => 5,
            ],
            [
                'account' => 5002000,
                'imputable' => 'N',
                'name' => 'GASTOS GENERALES',
                'parent_account' => 5,
            ],
            [
                'account' => 5003000,
                'imputable' => 'N',
                'name' => 'SERVICIOS PAGADOS',
                'parent_account' => 5,
            ],
            [
                'account' => 5004000,
                'imputable' => 'N',
                'name' => 'IMPUESTOS PAGADOS',
                'parent_account' => 5,
            ],
            [
                'account' => 5005000,
                'imputable' => 'N',
                'name' => 'FLETES Y ACARREOS',
                'parent_account' => 5,
            ],
            [
                'account' => 5006000,
                'imputable' => 'N',
                'name' => 'SEGUROS PAGADOS',
                'parent_account' => 5,
            ],
            [
                'account' => 5007000,
                'imputable' => 'N',
                'name' => 'SUELDOS Y JORNALES',
                'parent_account' => 5,
            ],
            [
                'account' => 5008000,
                'imputable' => 'N',
                'name' => 'ALQUILERES PAGADOS',
                'parent_account' => 5,
            ],
            [
                'account' => 5009000,
                'imputable' => 'N',
                'name' => 'INTERESES PAGADOS',
                'parent_account' => 5,
            ],
            [
                'account' => 5010000,
                'imputable' => 'N',
                'name' => 'COMISIONES PAGADAS',
                'parent_account' => 5,
            ],
            [
                'account' => 5011000,
                'imputable' => 'N',
                'name' => 'DESCUENTOS CEDIDOS',
                'parent_account' => 5,
            ],
            [
                'account' => 5012000,
                'imputable' => 'N',
                'name' => 'BONIFICACIONES CEDIDAS',
                'parent_account' => 5,
            ],
            [
                'account' => 5013000,
                'imputable' => 'N',
                'name' => 'GASTOS ',
                'parent_account' => 5,
            ],
            [
                'account' => 5014000,
                'imputable' => 'N',
                'name' => 'AMORTIZACIONES',
                'parent_account' => 5,
            ],
            [
                'account' => 5015000,
                'imputable' => 'N',
                'name' => 'VIATICOS',
                'parent_account' => 5,
            ],
            [
                'account' => 5016000,
                'imputable' => 'N',
                'name' => 'DIFERENICIA NEGATIVA DE CAMBIO',
                'parent_account' => 5,
            ],
            [
                'account' => 5017000,
                'imputable' => 'N',
                'name' => 'FALTANTE DE CAJA',
                'parent_account' => 5,
            ],
            [
                'account' => 5018000,
                'imputable' => 'N',
                'name' => 'FALTANTE DE MERCADERÍAS',
                'parent_account' => 5,
            ],
            [
                'account' => 5019000,
                'imputable' => 'N',
                'name' => 'DEUDORES INCOBRABLES',
                'parent_account' => 5,
            ],
        ]);
    }
}
