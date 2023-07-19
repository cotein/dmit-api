<?php

namespace Cotein\ApiAfip\Afip\FacturacionElectronica\Invoices;

use App\Src\Afip\WS\AfipWSFE;
use App\Src\Afip\FacturacionElectronica\Traits\WSAATrait;
use App\Src\Afip\WS\AfipWSFECRED;
use App\Src\Arba\Arba;

abstract class Invoice
{
    const CANTREG = 1;

    const LENGTH_CUIT = 11;

    const CONCEPTO_PRODUCTOS = 1;

    const PERCEPCION_IIBB_ID = 7;

    const PERCEPCION_IIBB_DESCRIPCION = 'Percep. Ingresos Brutos Bs.As.';

    /**TODO solucionar punto de venta */
    protected $PtoVta = 3;

    protected $CbteTipo;

    protected $Concepto = self::CONCEPTO_PRODUCTOS;

    /**
     * DocNro: Long (11) Nro. De identificación del comprador
     * requerido S
     *
     * @var mixed
     */
    protected $DocNro;

    protected $CbteDesde;

    protected $CbteHasta;

    /**
     * DocTipo: Int (2) Código de documento identificatorio del comprador
     * 80=CUIT | 96=DNI | 86=CUIL
     * @var mixed
     */
    protected $DocTipo;

    protected $FECAEDetRequest = [];

    protected $FeCabReq = [];

    /**
     * CbteFch: Fecha del comprobante (yyyymmdd)
     *   Para concepto igual a 1, la fecha de
     *   emisión del comprobante puede ser hasta
     *   5 días anteriores o posteriores respecto
     *   de la fecha de generación: La misma no
     *   podrá exceder el mes de presentación. Si>
     *   se indica Concepto igual a 2 ó 3 puede
     *   ser hasta 10 días anteriores o posteriores
     *   a la fecha de generación. Si no se envía
     *   la fecha del comprobante se asignará la
     *   fecha de proceso.
     *   Para comprobantes del tipo MiPyMEs
     *   (FCE) del tipo Factura, la fecha de
     *   emisión del comprobante debe ser desde
     *   5 días anteriores y hasta 1 día posterior
     *   respecto de la fecha de generación. Para
     *   notas de débito y crédito es hasta 5 dias
     *   anteriores y tiene que ser posterior o igual
     *   a la fecha del comprobante asociado
     *
     *
     * @var String (8)
     */
    protected $CbteFch;

    protected $CbteFch_MiPyme;

    /**
     * ImpTotal
     * Double (13+2)
     * Importe total del comprobante, Debe ser
     * igual a Importe neto no gravado + Importe
     * exento + Importe neto gravado + todos los
     * campos de IVA al XX% + Importe de
     * tributos[''].
     * @var Double (13+2)
     */
    protected $ImpTotal = 0;

    /**
     * ImpTotConc
     * Double (13+2)
     * Importe neto no gravado.
     * Debe ser menor o igual a Importe total y
     * no puede ser menor a cero.
     * No puede ser mayor al Importe total de la
     * operación ni menor a cero (0).
     * Para comprobantes tipo C debe ser igual a cero (0).
     * Para comprobantes tipo Bienes Usados –
     * Emisor Monotributista este campo
     * corresponde al importe subtotal.

     * @var Double (13+2)
     */
    protected $ImpTotConc = 0;

    /**
     * ImpNeto
     * Double (13+2)
     * Importe neto gravado. Debe ser menor o
     * igual a Importe total y no puede ser menor
     * a cero. Para comprobantes tipo C este
     * campo corresponde al Importe del SubTotal.
     * Para comprobantes tipo Bienes Usados –>
     * Emisor Monotributista no debe informarse
     * o debe ser igual a cero (0).
     * @var Double (13+2)
     */
    protected $ImpNeto = 0;

    /**
     * ImpOpEx
     * Double (13+2)
     * Importe exento. Debe ser menor o igual a
     * Importe total y no puede ser menor a cero.
     * Para comprobantes tipo C debe ser igual a cero (0).
     * Para comprobantes tipo Bienes Usados –
     * Emisor Monotributista no debe informarse
     * o debe ser igual a cero (0)
     * @var Double (13+2)
     */
    protected $ImpOpEx = 0;

    /**
     * ImpTrib
     * Double (13+2)
     * Suma de los importes del array de
     * tributos['']
     * @var Double (13+2)
     */
    protected $ImpTrib = 0;

    /**
     * IVA Array
     * Array para informar las alícuotas y sus
     * importes asociados a un comprobante
     * <AlicIva>.
     * Para comprobantes tipo C y Bienes
     * Usados – Emisor Monotributista no debe
     * informar el array.
     *
     * @var mixed
     */
    protected $IVA;

    /**
     * ImpIVA
     * Double (13+2)
     * Suma de los importes del array de IVA.
     * Para comprobantes tipo C debe ser igual a cero (0).
     * Para comprobantes tipo Bienes Usados –
     * Emisor Monotributista no debe informarse
     * o debe ser igual a cero (0)
     * @var Double (13+2)
     */
    protected $ImpIVA = 0;

    protected $FchServDesde = '';

    protected $FchServHasta = '';

    /**
     * FchVtoPago
     * String (8)
     * Fecha de vencimiento del pago servicio
     * a facturar. Dato obligatorio para
     * concepto 2 o 3 (Servicios / Productos y
     * Servicios) o Facturas del tipo MiPyMEs
     * (FCE). Formato yyyymmdd. Debe ser
     * igual o posterior a la fecha del
     * comprobante.
     * @var String (8)
     */
    protected $FchVtoPago;

    /**
     * MonId
     * String (3)
     * Código de moneda del comprobante.
     * Consultar método
     * FEParamGetTiposMonedas para valores
     * posibles

     * @var String (3)
     */
    protected $MonId = 'PES';

    /**
     * MonCotiz Double (4+6)
     * Cotización de la moneda informada. Para
     * PES, pesos argentinos la misma debe
     * ser 1
     *
     * @var Double (4+6)
     */
    protected $MonCotiz = 1;

    protected $CbtesAsoc  = [

        'CbteAsoc' =>
        [
            'Tipo' => '',
            'PtoVta' => '',
            'Nro' => '',
            'Cuit'    => '',
            'CbteFch' => ''
        ]
    ];


    protected $Tributos  = [];

    protected $Iva = [

        'AlicIva' => [

            'Id' =>  '',
            'BaseImp' =>  '',
            'Importe' => '',
        ]
    ];


    /**
     * afipWSFECRED_obligado
     *
     * @var String 'S' ó 'N'
     */
    protected $afipWSFECRED_obligado = null;

    /**
     * afipWSFECRED_montoUmbral, si afipWSFECRED_obligado es 'S'
     * retorna el monto a partir del cual hay que emitir
     * factura de Crédito electrónica (MiPyme)
     * @var String
     */
    protected $afipWSFECRED_montoUmbral = 0;

    /**
     * items Guarda el detalle de los productos a facturar
     *
     * @var mixed
     */
    protected $items;

    /**
     * afipWSFE Web Service de factura electrónica
     *
     * @var mixed
     */
    public $afipWSFE;

    /**
     * afipWSFECRED Web Service de factura de Crédito electrónica (MiPyme)
     *
     * @var mixed
     */
    public $afipWSFECRED;

    /**
     * Web Service de ARBA
     *
     * @var mixed
     */
    protected $arbaWS;

    public function __construct($environment)
    {
        $this->afipWSFE = new AfipWSFE($environment);

        $this->afipWSFECRED = new AfipWSFECRED($environment);

        //$this->arbaWS = new Arba;
    }

    /** SETTERS */
    /**
     * setNetoImport: Guarda el importe neto para procesos
     * posteriores.
     *
     * @param  mixed $neto_import
     * @return void
     */
    public function setNetoImport($neto_import)
    {
        $this->ImpNeto = $neto_import;
    }

    public function setDocNro($doc_nro)
    {
        $this->DocNro = $doc_nro;

        return $this;
    }

    public function setInvoiceDate($invoice_date)
    {
        $this->CbteFch = $invoice_date;

        return $this;
    }

    public function setCbteNumero($cbteNro)
    {
        $this->CbteDesde = $cbteNro;

        $this->CbteHasta = $cbteNro;

        return $this;
    }

    public function setImpTrib($ImpTrib)
    {
        $this->ImpTrib = $ImpTrib;
    }

    public function setDocTipo($DocTipo)
    {
        $this->DocTipo = $DocTipo;

        return $this;
    }

    public function setCbteFchMiPyme($invoice_date)
    {
        $this->CbteFch_MiPyme = $invoice_date;

        return $this;
    }

    public function setItems($items)
    {
        $this->items = $items;

        return $this;
    }

    public function setCbtesAsoc($CbtesAsoc)
    {
        $this->CbtesAsoc = collect($CbtesAsoc)->map(function ($c) {

            return
                [
                    'Tipo' => $c['Tipo'],
                    'PtoVta' => $c['PtoVta'],
                    'Nro' => $c['Nro'],
                    'Cuit'    => (int) $c['Cuit'],
                    'CbteFch' => $c['CbteFch']
                ];
        })->toArray();
    }

    public function setImpTotal($importe)
    {
        $this->ImpTotal = (float) $this->ImpTotal + (float) $importe;
    }

    public function setFeCabReq()
    {
        $this->FeCabReq['CantReg'] = (int) self::CANTREG;
        $this->FeCabReq['PtoVta'] = (int) $this->PtoVta;
        $this->FeCabReq['CbteTipo'] = (int) $this->CbteTipo;
    }

    public function setIVA($arrayIva)
    {
        $this->IVA = collect($arrayIva)->values()->toArray();
    }

    public function setImpIVA($importe)
    {
        $this->ImpIVA = $importe;
    }

    public function setTributos($Tributos)
    {
        $this->Tributos = $Tributos;
    }

    public function setFECAEDetRequest()
    {
        $this->FECAEDetRequest['Concepto']      = (int)self::CONCEPTO_PRODUCTOS; //Concepto puede ser 1 produ| 86=CUIL
        $this->FECAEDetRequest['DocNro']        = (float)$this->DocNro; //Documento NÃºmero:CUIT
        $this->FECAEDetRequest['CbteDesde']     = (float)$this->CbteDesde; //Comprobante desde
        $this->FECAEDetRequest['CbteHasta']     = (float)$this->CbteHasta; //Comprobante hasta
        $this->FECAEDetRequest['CbteFch']       = (string)$this->CbteFch; //Fecha del comprobante
        $this->FECAEDetRequest['ImpTotal']      = (float)$this->ImpTotal; //Importe total: nng + ex + ng + todos los ivas + tributos
        $this->FECAEDetRequest['ImpTotConc']    = (float)$this->ImpTotConc; //Importe neto NO gravado
        $this->FECAEDetRequest['ImpNeto']       = (float)$this->ImpNeto; //Importe neto gravadoctos, 2 servicios o 3 productos y servicios
        $this->FECAEDetRequest['DocTipo']       = (int)$this->DocTipo; //Documento tipo: 80=CUIT | 96=DNI
        $this->FECAEDetRequest['ImpOpEx']       = (float)$this->ImpOpEx; //Importe exento
        $this->FECAEDetRequest['ImpTrib']       = (float)$this->ImpTrib; //Suma de los importes del Array Tributos
        $this->FECAEDetRequest['ImpIVA']        = (float)$this->ImpIVA; //Suma de los importes del Array IVA
        $this->FECAEDetRequest['FechServDesde'] = $this->FchServDesde; //Fecha inicio del Servicio - Si se factura productos no es necesario
        $this->FECAEDetRequest['FechServHasta'] = $this->FchServHasta; //Fecha fin del Servicio
        $this->FECAEDetRequest['FchVtoPago']    = (string)$this->FchVtoPago; //Fecha de Vencimiento de pago: Debe ser >= a la fecha del comprobante
        $this->FECAEDetRequest['MonId']         = (string)$this->MonId; //Moneda del comprobante
        $this->FECAEDetRequest['MonCotiz']      = (float)$this->MonCotiz; //CotizaciÃ³n de la moneda
        $this->FECAEDetRequest['Iva']           = $this->IVA;

        if ($this->ImpTrib > 0) {
            $this->FECAEDetRequest['Tributos'] = $this->Tributos;
        }

        $debitos_creditos = collect([2, 3, 7, 8, 12, 13]);

        if ($debitos_creditos->contains((int) $this->CbteTipo)) {
            $this->FECAEDetRequest['CbtesAsoc'] = $this->CbtesAsoc;
        }
    }

    public function operationsWithItems()
    {
        $netoImport = $this->calculateNetoImport();

        $this->setNetoImport($netoImport);

        $arrayIva = $this->calculateArrayAlicIva();

        $this->setIVA($arrayIva);

        $ivaImport = $this->calculateImpIVA($arrayIva);

        $this->setImpIVA($ivaImport);

        $this->setImpTotal($netoImport);

        $this->setImpTotal($ivaImport);

        return $this;
    }

    public function calculateImpIVA($array)
    {
        $ivas = collect($array);

        if ($ivas->isNotEmpty()) {
            return $ivas->sum('Importe');
        }

        return 0;
    }

    public function calculateNetoImport()
    {
        $items = collect($this->items);

        if ($items->isNotEmpty()) {
            return $items->sum('neto_import');
        }

        return 0;
    }

    public function calculateArrayAlicIva()
    {
        $items = collect($this->items);

        return $items->groupBy('iva_afip_code')->map(function ($iva) {

            $BaseImp = collect($iva)->reduce(function ($acc, $item) {
                return $acc + $item['neto_import'];
            });

            $Importe = collect($iva)->reduce(function ($acc, $item) {
                return $acc + $item['iva_import'];
            });

            $Id = collect($iva)->reduce(function ($acc, $item) {
                return $item['iva_afip_code'];
            });

            return [
                'Id' => $Id,
                'BaseImp' => $BaseImp,
                'Importe' => $Importe
            ];
        })->values()->all();
    }

    public function isCUIT()
    {
        return strlen($this->DocNro) == self::LENGTH_CUIT;
    }
    /**
     * requiereFacturaMiPyme
     *
     * @param  mixed $customerCuit
     * @param  mixed $emitionDate
     * @return void
     * si obligado es igual a 'S' entonces setea las variables
     * para luego calcular el comprobante MiPyme
     */
    public function requiereFacturaMiPyme()
    {
        if (!$this->isCUIT()) {
            return false;
        }

        $result = $this->afipWSFECRED->consultarMontoObligadoRecepcion($this->DocNro, $this->CbteFch_MiPyme);

        if ($result->arrayErroresFormato) {
            return false;
        }

        if ($result->obligado == 'S') {

            $this->afipWSFECRED_obligado = $result->obligado;

            $this->afipWSFECRED_montoUmbral = $result->montoDesde;

            return $this->isMiPyme();
        }

        return false;
    }

    public function isMiPyme(): bool
    {
        if ($this->afipWSFECRED_montoUmbral >= $this->ImpNeto) {
            return true;
        }

        return false;
    }


    /**
     * ultimoAutorizado
     *
     * @return Afip response
     */
    public function ultimoAutorizado()
    {
        $result = $this->afipWSFE->FECompUltimoAutorizado($this->PtoVta, $this->CbteTipo);

        return $result->CbteNro;
    }

    public function getCaeOnAfip(): array
    {
        $this->setFECAEDetRequest();

        $this->setFeCabReq();

        $FECAESolicitarResult = $this->afipWSFE->getCaeOnAfip($this->FeCabReq, $this->FECAEDetRequest);
        //dd($FECAESolicitarResult);
        //$this->afipWSFE->__checkErrors('FECAESolicitar', $FECAESolicitarResult);

        return [
            'FECAESolicitarResult' => $FECAESolicitarResult,
            'tributos' => $this->Tributos
        ];
    }

    public function FEParamGetTiposTributos()
    {
        return $this->afipWSFE->FEParamGetTiposTributos();
    }

    ###### MÉTODOS ARBA WEB SERVICE #####

    public function setPercepIIBB()
    {

        /**
         * TODO
         * por ahora no realizar percepcionde de iibb
         * se puede manejar desde la bbdd
         */

        return $this;

        if (!$this->isCUIT()) {
            return $this;
        }

        $arba_response = $this->arbaWS->alicuota_sujeto($this->DocNro);

        $alicuota = $this->arbaWS->getAlicuota($arba_response);

        if ($this->apply_alicuota_iibb($alicuota)) {

            $alicuota = str_replace(',', '.', $alicuota);

            $this->Tributos['Tributo']['Id'] =  (int)self::PERCEPCION_IIBB_ID;
            $this->Tributos['Tributo']['Desc'] =  self::PERCEPCION_IIBB_DESCRIPCION;
            $this->Tributos['Tributo']['BaseImp'] =  (float)$this->ImpNeto;
            $this->Tributos['Tributo']['Alic'] =  (float) $alicuota;
            $this->Tributos['Tributo']['Importe'] =  (float)$this->ImpNeto * (float) $alicuota / 100;

            $this->ImpTrib = (float)$this->ImpNeto * (float) $alicuota / 100;

            $this->setImpTotal($this->ImpTrib);
        }

        return $this;
    }

    public function apply_alicuota_iibb($alicuota)
    {
        $zero = '0.00';

        $alicuota = str_replace(',', '.', $alicuota);

        if ($alicuota == $zero) {
            return false;
        }

        if (floor($alicuota) > floor($zero)) {
            return true;
        }

        if (floor($zero) > floor($alicuota)) {
            return false;
        }

        $decimal_a = explode('.', $alicuota - floor($alicuota));
        $decimal_b = explode('.', $zero);

        if ((int)$decimal_a[1] > (int)$decimal_b[1]) {
            return true;
        }

        if ((int)$decimal_b[1] > (int)$decimal_a[1]) {
            return false;
        }

        return false;
    }

    public function digVerificador($digVerificador)
    {
        /*
            RUTINA PARA EL CALCULO DEL DIGITO VERIFICADOR
            Se considera para efectuar el cálculo el siguiente ejemplo:
            01234567890
            Etapa 1: Comenzar desde la izquierda, sumar todos los caracteres ubicados en las posiciones impares.
            0 + 2 + 4 + 6 + 8 + 0 = 20
            Etapa 2: Multiplicar la suma obtenida en la etapa 1 por el número 3.
            20 x 3 = 60
            Etapa 3: Comenzar desde la izquierda, sumar todos los caracteres que están ubicados en las posiciones pares.
            1 + 3 + 5+ 7 + 9 = 25
            Etapa 4: Sumar los resultados obtenidos en las etapas 2 y 3.
            60 + 25 = 85
            Etapa 5: Buscar el menor número que sumado al resultado obtenido en la etapa 4 dé un número múltiplo de 10. Este será el valor del dígito verificador del módulo 10.
            85 + 5 = 90
            De esta manera se llega a que el número 5 es el dígito verificador módulo 10 para el código 01234567890
            Siendo el resultado final:
            012345678905

            Código de barras que usa "Interleaved 2 of 5"

            - C.U.I.T. (Clave Unica de Identificación Tributaria) del emisor (11 caracteres).
            - Código de tipo de comprobante (2 caracteres).
            - Punto de venta (4 caracteres).
            - Código de Autorización de Impresión (14 caracteres).
            - Fecha de vencimiento (8 caracteres). YYYYMMDD
            - Dígito verificador (1 carácter).
			*/


        $sumaPar = 0;
        $sumaImpar = 0;
        for ($i = 0; $i < strlen($digVerificador); $i++) {
            if (($i + 1) % 2 != 0) {
                $sumaImpar = $sumaImpar + $digVerificador[$i];
            } else {
                $sumaPar = $sumaPar + $digVerificador[$i];
            }
        }
        $etapa2 = $sumaImpar * 3;
        $etapa4 = $etapa2 + $sumaPar;

        for ($i = 0; $i < 10; $i++) {
            if (($etapa4 + $i) % 10 == 0) {
                return $i;
            }
        }
    }

    /**
     * [tipoCbteFormatoAfip Devuelve el
     * codigo del comprobante con el formato
     * de dos dígitos.]
     * @param  [type] $tc [description]
     * @return [type]     [description]
     */
    public function cbteTipoFormatoAfip($tipoCbte)
    {
        if ($tipoCbte < 10) {
            return (string)'0' . $tipoCbte;
        } else {
            return (string)$tipoCbte;
        }
    }
}
