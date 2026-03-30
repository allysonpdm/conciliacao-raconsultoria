<?php

namespace App\Services;

use App\Models\Conciliacao;
use App\Models\Empresa;
use App\Models\Pagamento;
use App\Services\Conciliacao\GenerateFileImport;
use App\Services\Conciliacao\Store;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Knp\Snappy\Pdf;

class ConciliacaoService
{
    public function store(
        Empresa $empresa,
        string $pathFile,
    ): Conciliacao {
        return Store::execute(
            empresa: $empresa,
            pathFile: $pathFile
        );
    }

    public function relatorioHtml(Conciliacao $conciliacao): View
    {
        return view(
            view: 'relatorios.conciliacao',
            data: ['contas' => $conciliacao->auditoria]
        );
    }

    public function relatorioPdf(Conciliacao $conciliacao): string
    {
        $html = $this->relatorioHtml(conciliacao: $conciliacao);

        $pdf = new Pdf('/usr/bin/wkhtmltopdf');

        $pdf->setOption('print-media-type', true);
        $pdf->setOption('page-size', 'A4');
        $pdf->setOption('margin-top', '20mm');
        $pdf->setOption('margin-bottom', '30mm');
        $pdf->setOption('margin-left', '15mm');
        $pdf->setOption('margin-right', '15mm');
        $pdf->setOption('enable-local-file-access', true);

        return $pdf->getOutputFromHtml($html->render());
    }

    public function generateImportFile(Conciliacao $conciliacao): string
    {
        return GenerateFileImport::execute('ultima', $conciliacao);
    }
}
