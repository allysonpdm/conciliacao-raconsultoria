@once
    @push('styles')
        <link rel="stylesheet" href="{{ asset('css/filament-pagamentos.css') }}">
    @endpush
@endonce

@if ($record->pagamentos->isEmpty())
    <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden bg-white dark:bg-gray-900 p-4">
        <div class="text-sm text-gray-600 dark:text-gray-300">Nenhum pagamento registrado.</div>
    </div>
@else
    <div
        class="w-full border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden bg-white dark:bg-gray-900 filament-pagamentos-container">
        <div class="w-full overflow-x-auto">
            <table
                class="filament-pagamentos-table min-w-full w-full divide-y divide-gray-200 dark:divide-gray-700 table-auto">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th
                            class="px-8 py-4 text-left text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Data</th>
                        <th
                            class="px-8 py-4 text-left text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider min-w-[220px] md:min-w-[340px]">
                            Documento</th>
                        <th
                            class="px-8 py-4 text-right text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider min-w-[120px]">
                            Valor Pago</th>
                        <th
                            class="px-8 py-4 text-center text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider min-w-[90px]">
                            Parcela</th>
                        <!--
                            <th class="px-8 py-4 text-left text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider min-w-[160px]">Tipo</th>
                        -->
                    </tr>
                </thead>

                <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach ($record->pagamentos as $pagamento)
                        <tr
                            class="odd:bg-white even:bg-gray-50 dark:odd:bg-gray-900 dark:even:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800">
                            <td class="px-8 py-5 whitespace-nowrap text-sm text-gray-700 dark:text-gray-100">
                                {{ optional($pagamento->data)->format('d/m/Y') }}</td>
                            <td
                                class="px-8 py-5 whitespace-normal break-words text-sm text-gray-700 dark:text-gray-100 min-w-[220px] md:min-w-[340px]">
                                {{ $pagamento->doc ?? '-' }}</td>
                            <td
                                class="px-8 py-5 whitespace-nowrap text-sm text-right text-gray-700 dark:text-gray-100 min-w-[120px]">
                                R$ {{ number_format($pagamento->valor_pago ?? 0, 2, ',', '.') }}</td>
                            <td
                                class="px-8 py-5 whitespace-nowrap text-sm text-center text-gray-700 dark:text-gray-100 min-w-[90px]">
                                {{ $pagamento->parcela ?? '-' }}</td>
                            <!--
                            <td class="px-8 py-5 whitespace-nowrap text-sm text-gray-700 dark:text-gray-100 min-w-[160px]">
                                @php
                                    $tipo = strtolower(trim((string) ($pagamento->tipo ?? '')));
                                    $badgeClasses = 'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ';
                                    if ($tipo === 'credito' || $tipo === 'crédito') {
                                        $badgeClasses .=
                                            'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
                                    } elseif ($tipo === 'debito' || $tipo === 'débito') {
                                        $badgeClasses .= 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
                                    } else {
                                        $badgeClasses .=
                                            'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-100';
                                    }
                                @endphp

                                <span class="filament-pagamentos-badge {{ $badgeClasses }} {{ $tipo === 'credito' || $tipo === 'crédito' ? 'filament-pagamentos-badge--credito' : ($tipo === 'debito' || $tipo === 'débito' ? 'filament-pagamentos-badge--debito' : 'filament-pagamentos-badge--default') }}">{{ $pagamento->tipo ? ucfirst($pagamento->tipo) : '-' }}</span>
                            </td>
                            -->
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
