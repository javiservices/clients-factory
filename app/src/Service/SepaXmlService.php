<?php

namespace App\Service;

/**
 * Servicio para la generación de Remesas Bancarias SEPA en formato XML ISO 20022
 * pain.008.001.02 (Adeudo Directo SEPA - Esquema Básico CORE / Norma 19).
 */
class SepaXmlService
{
    /**
     * Genera el contenido XML de la remesa SEPA pain.008.001.02
     *
     * @param array $creditor Datos de la empresa acreedora (name, cif, iban, bic, creditorId)
     * @param array $transactions Lista de transacciones [
     *   'id' => int|string,
     *   'amount' => float,
     *   'client_id' => int,
     *   'client_name' => string,
     *   'client_iban' => string,
     *   'mandate_date' => \DateTimeInterface|null,
     *   'concept' => string
     * ]
     * @param \DateTimeInterface|null $collectionDate Fecha de cobro deseada
     * @return string XML formateado
     */
    public function generateDirectDebitXml(
        array $creditor,
        array $transactions,
        ?\DateTimeInterface $collectionDate = null
    ): string {
        $now = new \DateTime();
        $colDate = $collectionDate ?: (new \DateTime())->modify('+2 weekdays');

        $nbOfTxs = count($transactions);
        $ctrlSum = 0.0;
        foreach ($transactions as $tx) {
            $ctrlSum += (float) $tx['amount'];
        }

        $msgId = 'CF' . $now->format('YmdHis') . substr(uniqid(), -4);
        $pmtInfId = 'PMT' . $now->format('YmdHis');

        $creditorName = $this->sanitizeText($creditor['name'] ?? 'GIMNASIO SANTA MARIA', 70);
        $creditorCif  = preg_replace('/[^A-Za-z0-9]/', '', (string) ($creditor['cif'] ?? 'B00000000'));
        $creditorIban = preg_replace('/[^A-Za-z0-9]/', '', (string) ($creditor['iban'] ?? ''));
        $creditorBic  = preg_replace('/[^A-Za-z0-9]/', '', (string) ($creditor['bic'] ?? ''));

        // Identificador del acreedor SEPA (AT-02)
        $creditorId = $creditor['creditor_id'] ?? null;
        if (!$creditorId) {
            $creditorId = 'ES00000' . $creditorCif;
        }
        $creditorId = preg_replace('/[^A-Za-z0-9]/', '', (string) $creditorId);

        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        $root = $doc->createElementNS('urn:iso:std:iso:20022:tech:xsd:pain.008.001.02', 'Document');
        $root->setAttributeNS('http://www.w3.org/2001/XMLSchema-instance', 'xsi:schemaLocation', 'urn:iso:std:iso:20022:tech:xsd:pain.008.001.02 pain.008.001.02.xsd');
        $doc->appendChild($root);

        $cstmrDrctDbtInitn = $doc->createElement('CstmrDrctDbtInitn');
        $root->appendChild($cstmrDrctDbtInitn);

        // ─── Group Header ───
        $grpHdr = $doc->createElement('GrpHdr');
        $cstmrDrctDbtInitn->appendChild($grpHdr);

        $grpHdr->appendChild($doc->createElement('MsgId', $msgId));
        $grpHdr->appendChild($doc->createElement('CreDtTm', $now->format('Y-m-d\TH:i:s')));
        $grpHdr->appendChild($doc->createElement('NbOfTxs', (string) $nbOfTxs));
        $grpHdr->appendChild($doc->createElement('CtrlSum', number_format($ctrlSum, 2, '.', '')));

        $initgPty = $doc->createElement('InitgPty');
        $grpHdr->appendChild($initgPty);
        $initgPty->appendChild($doc->createElement('Nm', $creditorName));
        $orgId = $doc->createElement('OrgId');
        $othr = $doc->createElement('Othr');
        $othr->appendChild($doc->createElement('Id', $creditorCif));
        $orgId->appendChild($othr);
        $initgPtyId = $doc->createElement('Id');
        $initgPtyId->appendChild($orgId);
        $initgPty->appendChild($initgPtyId);

        // ─── Payment Information ───
        $pmtInf = $doc->createElement('PmtInf');
        $cstmrDrctDbtInitn->appendChild($pmtInf);

        $pmtInf->appendChild($doc->createElement('PmtInfId', $pmtInfId));
        $pmtInf->appendChild($doc->createElement('PmtMtd', 'DD'));
        $pmtInf->appendChild($doc->createElement('NbOfTxs', (string) $nbOfTxs));
        $pmtInf->appendChild($doc->createElement('CtrlSum', number_format($ctrlSum, 2, '.', '')));

        $pmtTpInf = $doc->createElement('PmtTpInf');
        $pmtInf->appendChild($pmtTpInf);
        $svcLvl = $doc->createElement('SvcLvl');
        $svcLvl->appendChild($doc->createElement('Cd', 'SEPA'));
        $pmtTpInf->appendChild($svcLvl);
        $lclInstrm = $doc->createElement('LclInstrm');
        $lclInstrm->appendChild($doc->createElement('Cd', 'CORE'));
        $pmtTpInf->appendChild($lclInstrm);
        $pmtTpInf->appendChild($doc->createElement('SeqTp', 'RCUR'));

        $pmtInf->appendChild($doc->createElement('ReqdColltnDt', $colDate->format('Y-m-d')));

        // Creditor (Acreedor)
        $cdtr = $doc->createElement('Cdtr');
        $cdtr->appendChild($doc->createElement('Nm', $creditorName));
        $pmtInf->appendChild($cdtr);

        // Creditor Account (Cuenta del acreedor)
        $cdtrAcct = $doc->createElement('CdtrAcct');
        $cdtrAcctId = $doc->createElement('Id');
        $cdtrAcctId->appendChild($doc->createElement('IBAN', $creditorIban ?: 'ES0000000000000000000000'));
        $cdtrAcct->appendChild($cdtrAcctId);
        $pmtInf->appendChild($cdtrAcct);

        // Creditor Agent (Banco del acreedor)
        $cdtrAgt = $doc->createElement('CdtrAgt');
        $finInstnId = $doc->createElement('FinInstnId');
        if (!empty($creditorBic)) {
            $finInstnId->appendChild($doc->createElement('BIC', $creditorBic));
        } else {
            $otherBic = $doc->createElement('Othr');
            $otherBic->appendChild($doc->createElement('Id', 'NOTPROVIDED'));
            $finInstnId->appendChild($otherBic);
        }
        $cdtrAgt->appendChild($finInstnId);
        $pmtInf->appendChild($cdtrAgt);

        $pmtInf->appendChild($doc->createElement('ChrgBr', 'SLEV'));

        // Creditor Scheme Identification (Identificador de Acreedor)
        $cdtrSchmeId = $doc->createElement('CdtrSchmeId');
        $schmeId = $doc->createElement('Id');
        $prvtId = $doc->createElement('PrvtId');
        $schmeOthr = $doc->createElement('Othr');
        $schmeOthr->appendChild($doc->createElement('Id', $creditorId));
        $schmeNm = $doc->createElement('SchmeNm');
        $schmeNm->appendChild($doc->createElement('Prtry', 'SEPA'));
        $schmeOthr->appendChild($schmeNm);
        $prvtId->appendChild($schmeOthr);
        $schmeId->appendChild($prvtId);
        $cdtrSchmeId->appendChild($schmeId);
        $pmtInf->appendChild($cdtrSchmeId);

        // ─── Direct Debit Transactions ───
        foreach ($transactions as $index => $tx) {
            $amount = (float) $tx['amount'];
            if ($amount <= 0.001) {
                continue;
            }

            $txIban = preg_replace('/[^A-Za-z0-9]/', '', (string) ($tx['client_iban'] ?? ''));
            if (empty($txIban)) {
                continue;
            }

            $txNm = $this->sanitizeText($tx['client_name'] ?? ('Cliente ' . $tx['client_id']), 70);
            $concept = $this->sanitizeText($tx['concept'] ?? 'Cuota gimnasio', 140);
            $endToEnd = 'E2E-' . ($tx['id'] ?? ($index + 1)) . '-' . ($tx['client_id'] ?? '0');
            $endToEnd = substr($endToEnd, 0, 35);

            $mandateId = 'MANDATO-' . ($tx['client_id'] ?? ($index + 1));
            $mandateDate = ($tx['mandate_date'] instanceof \DateTimeInterface)
                ? $tx['mandate_date']->format('Y-m-d')
                : '2024-01-01';

            $drctDbtTxInf = $doc->createElement('DrctDbtTxInf');
            $pmtInf->appendChild($drctDbtTxInf);

            // Payment Identification
            $pmtId = $doc->createElement('PmtId');
            $pmtId->appendChild($doc->createElement('EndToEndId', $endToEnd));
            $drctDbtTxInf->appendChild($pmtId);

            // Instructed Amount
            $instdAmt = $doc->createElement('InstdAmt', number_format($amount, 2, '.', ''));
            $instdAmt->setAttribute('Ccy', 'EUR');
            $drctDbtTxInf->appendChild($instdAmt);

            // Direct Debit Transaction (Mandate info)
            $drctDbtTx = $doc->createElement('DrctDbtTx');
            $mndtRltdInf = $doc->createElement('MndtRltdInf');
            $mndtRltdInf->appendChild($doc->createElement('MndtId', substr($mandateId, 0, 35)));
            $mndtRltdInf->appendChild($doc->createElement('DtOfSgntr', $mandateDate));
            $drctDbtTx->appendChild($mndtRltdInf);
            $drctDbtTxInf->appendChild($drctDbtTx);

            // Debtor Agent
            $dbtrAgt = $doc->createElement('DbtrAgt');
            $dbtrFinInstnId = $doc->createElement('FinInstnId');
            $dbtrOthr = $doc->createElement('Othr');
            $dbtrOthr->appendChild($doc->createElement('Id', 'NOTPROVIDED'));
            $dbtrFinInstnId->appendChild($dbtrOthr);
            $dbtrAgt->appendChild($dbtrFinInstnId);
            $drctDbtTxInf->appendChild($dbtrAgt);

            // Debtor (Cliente)
            $dbtr = $doc->createElement('Dbtr');
            $dbtr->appendChild($doc->createElement('Nm', $txNm));
            $drctDbtTxInf->appendChild($dbtr);

            // Debtor Account (IBAN del cliente)
            $dbtrAcct = $doc->createElement('DbtrAcct');
            $dbtrAcctId = $doc->createElement('Id');
            $dbtrAcctId->appendChild($doc->createElement('IBAN', $txIban));
            $dbtrAcct->appendChild($dbtrAcctId);
            $drctDbtTxInf->appendChild($dbtrAcct);

            // Remittance Information (Concepto del recibo)
            $rmtInf = $doc->createElement('RmtInf');
            $rmtInf->appendChild($doc->createElement('Ustrd', $concept));
            $drctDbtTxInf->appendChild($rmtInf);
        }

        return $doc->saveXML();
    }

    /**
     * Sanitiza y translitera texto para cumplir con el estándar bancario SEPA (ASCII / EPC).
     * Reemplaza tildes y caracteres especiales para evitar rechazos en pasarelas bancarias.
     */
    public function sanitizeText(string $text, int $maxLength = 70): string
    {
        $text = trim($text);

        // Reemplazar caracteres españoles comunes
        $replacements = [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
            'ç' => 'c', 'Ç' => 'C', '&' => 'y',
        ];
        $text = strtr($text, $replacements);

        // Transliteración a ASCII estándar
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($ascii !== false) {
            $text = $ascii;
        }

        // Permitir solo caracteres autorizados en EPC SEPA (A-Z, a-z, 0-9, espacio, y signos comunes / - ? : ( ) . , ' +)
        $text = preg_replace('/[^A-Za-z0-9\/\-\?\:\(\)\.\,\'\+\s]/', ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text);

        return substr(trim($text), 0, $maxLength);
    }
}

