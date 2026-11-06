@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex items-center justify-between">
        <div class="flex flex-1 justify-between sm:hidden">
            @if ($paginator->onFirstPage())
                <span class="btn btn-secondary btn-sm pointer-events-none opacity-50">
                    {!! __('pagination.previous') !!}
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="btn btn-secondary btn-sm" rel="prev">
                    {!! __('pagination.previous') !!}
                </a>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="btn btn-secondary btn-sm" rel="next">
                    {!! __('pagination.next') !!}
                </a>
            @else
                <span class="btn btn-secondary btn-sm pointer-events-none opacity-50">
                    {!! __('pagination.next') !!}
                </span>
            @endif
        </div>

        <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
            <p class="text-sm text-muted">
                {!! __('Showing') !!}
                <span class="font-medium">{{ $paginator->firstItem() }}</span>
                {!! __('to') !!}
                <span class="font-medium">{{ $paginator->lastItem() }}</span>
                {!! __('of') !!}
                <span class="font-medium">{{ $paginator->total() }}</span>
                {!! __('results') !!}
            </p>

            <div>
                <span class="inline-flex gap-1 rounded-md shadow-sm">
                    @if ($paginator->onFirstPage())
                        <span aria-disabled="true" class="btn btn-secondary btn-sm pointer-events-none opacity-50">
                            <span class="sr-only">{{ __('pagination.previous') }}</span>
                            <span aria-hidden="true">&lsaquo;</span>
                        </span>
                    @else
                        <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="btn btn-secondary btn-sm">
                            <span class="sr-only">{{ __('pagination.previous') }}</span>
                            <span aria-hidden="true">&lsaquo;</span>
                        </a>
                    @endif

                    @foreach ($elements as $element)
                        @if (is_string($element))
                            <span aria-disabled="true" class="btn btn-secondary btn-sm pointer-events-none opacity-50">
                                <span aria-hidden="true">{{ $element }}</span>
                            </span>
                        @endif

                        @if (is_array($element))
                            @foreach ($element as $page => $url)
                                @if ($page == $paginator->currentPage())
                                    <span aria-current="page" class="btn btn-primary btn-sm pointer-events-none">
                                        <span class="sr-only">{{ __('Go to page :page', ['page' => $page]) }}</span>
                                        <span aria-hidden="true">{{ $page }}</span>
                                    </span>
                                @else
                                    <a href="{{ $url }}" class="btn btn-secondary btn-sm" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">
                                        {{ $page }}
                                    </a>
                                @endif
                            @endforeach
                        @endif
                    @endforeach

                    @if ($paginator->hasMorePages())
                        <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="btn btn-secondary btn-sm">
                            <span class="sr-only">{{ __('pagination.next') }}</span>
                            <span aria-hidden="true">&rsaquo;</span>
                        </a>
                    @else
                        <span aria-disabled="true" class="btn btn-secondary btn-sm pointer-events-none opacity-50">
                            <span class="sr-only">{{ __('pagination.next') }}</span>
                            <span aria-hidden="true">&rsaquo;</span>
                        </span>
                    @endif
                </span>
            </div>
        </div>
    </nav>
@endif
