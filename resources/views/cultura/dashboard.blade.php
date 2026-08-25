<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Assessor Cultura GovIA | Radar Cultural</title>
    <style>
        :root{--bg:#07111f;--panel:#0d1b2d;--panel2:#10243a;--text:#f5f8fc;--muted:#9db0c8;--line:#203750;--accent:#36d399;--warning:#f6c453}*{box-sizing:border-box}body{margin:0;font-family:Inter,system-ui,-apple-system,Segoe UI,sans-serif;background:var(--bg);color:var(--text)}.shell{display:grid;grid-template-columns:240px 1fr;min-height:100vh}.side{border-right:1px solid var(--line);padding:24px 18px;background:#081523}.brand{font-weight:800;font-size:18px;margin-bottom:6px}.product{color:var(--accent);font-size:13px;margin-bottom:28px}.nav a{display:block;color:var(--muted);text-decoration:none;padding:11px 12px;border-radius:10px;margin:4px 0}.nav a.active{background:var(--panel2);color:var(--text)}main{padding:28px;max-width:1500px;width:100%;overflow:hidden}.top{display:flex;justify-content:space-between;gap:20px;align-items:center;margin-bottom:24px}.top h1{font-size:27px;margin:0 0 5px}.muted{color:var(--muted)}.badge{border:1px solid var(--line);padding:8px 12px;border-radius:999px;font-size:13px}.stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:20px}.card{background:var(--panel);border:1px solid var(--line);border-radius:16px;padding:18px;min-width:0}.stat strong{display:block;font-size:25px;margin-top:8px}.grid{display:grid;grid-template-columns:minmax(0,2fr) minmax(260px,1fr);gap:18px}.section-title{display:flex;justify-content:space-between;align-items:center;margin-bottom:14px}.section-title h2{font-size:18px;margin:0}.opp{padding:16px 0;border-top:1px solid var(--line)}.opp:first-of-type{border-top:0}.opp-head{display:flex;justify-content:space-between;gap:14px}.opp h3{font-size:16px;margin:0 0 6px}.score{font-weight:800;color:var(--accent);white-space:nowrap}.meta{font-size:13px;color:var(--muted);display:flex;gap:12px;flex-wrap:wrap}.cta{display:inline-block;margin-top:12px;padding:9px 12px;border-radius:9px;background:var(--accent);color:#032016;text-decoration:none;font-weight:700;font-size:13px}.limit{margin:12px 0}.bar{height:7px;background:#142a40;border-radius:99px;overflow:hidden;margin-top:7px}.bar span{display:block;height:100%;background:var(--accent)}.notice{background:#122338;border:1px solid var(--line);padding:14px;border-radius:12px;color:var(--muted);font-size:13px;margin-top:14px}@media(max-width:900px){.shell{grid-template-columns:1fr}.side{display:none}.stats{grid-template-columns:repeat(2,minmax(0,1fr))}.grid{grid-template-columns:1fr}main{padding:18px}}@media(max-width:520px){.stats{grid-template-columns:1fr}.top{align-items:flex-start;flex-direction:column}}
    </style>
</head>
<body>
<div class="shell">
    <aside class="side">
        <div class="brand">VITRINE IA PRO</div>
        <div class="product">Assessor Cultura GovIA</div>
        <nav class="nav">
            <a class="active" href="{{ route('cultura.dashboard') }}">Visao geral</a>
            <a href="#radar">Radar Cultural</a>
            <a href="{{ route('cultura.profile.edit') }}">Meu perfil cultural</a>
            <a href="#projetos">Projetos</a>
            <a href="#monitoramento">Monitoramento</a>
            <a href="#plano">Plano e limites</a>
        </nav>
    </aside>
    <main>
        <div class="top">
            <div><h1>Radar Cultural</h1><div class="muted">Oportunidades de Sao Paulo priorizadas para o seu perfil cultural.</div></div>
            <div class="badge">Plano: {{ ucfirst($planSlug) }} · 1 usuario</div>
        </div>

        <section class="stats">
            <div class="card stat"><span class="muted">Oportunidades ativas</span><strong>{{ $activeOpportunities }}</strong></div>
            <div class="card stat"><span class="muted">Exibidas no radar</span><strong>{{ $radar->count() }}@if(($limits['radar_limit'] ?? null) !== null)/{{ $limits['radar_limit'] }}@endif</strong></div>
            <div class="card stat"><span class="muted">Projetos ativos</span><strong>0@if(($limits['active_monitoring_limit'] ?? null) !== null)/{{ $limits['active_monitoring_limit'] }}@endif</strong></div>
            <div class="card stat"><span class="muted">Cobertura</span><strong>SP</strong></div>
        </section>

        <div class="grid">
            <section class="card" id="radar">
                <div class="section-title"><h2>Melhores oportunidades para voce</h2><span class="muted">ordenadas por aderencia</span></div>
                @forelse($radar as $item)
                    @php($o = $item['opportunity'])
                    <article class="opp">
                        <div class="opp-head">
                            <div><h3>{{ $o->title }}</h3><div class="meta"><span>{{ $o->organization ?: $o->source_name }}</span>@if($o->closes_at)<span>Prazo: {{ $o->closes_at->format('d/m/Y') }}</span>@endif @if($o->funding_max)<span>Ate R$ {{ number_format((float)$o->funding_max, 2, ',', '.') }}</span>@endif</div></div>
                            <div class="score">{{ $item['score'] !== null ? $item['score'].'%' : 'Perfil pendente' }}</div>
                        </div>
                        @if($planSlug === 'gratuito')
                            <a class="cta" href="#plano">Quero participar · ver planos</a>
                        @else
                            <a class="cta" href="{{ $o->source_url }}" target="_blank" rel="noopener">Analisar oportunidade</a>
                        @endif
                    </article>
                @empty
                    <div class="notice">O Radar ainda nao possui oportunidades publicadas. Fontes em revisao nao sao exibidas ao cliente.</div>
                @endforelse
            </section>

            <aside>
                <section class="card" id="plano">
                    <div class="section-title"><h2>Seu plano</h2></div>
                    <div class="limit"><strong>Radar Cultural</strong><div class="muted">{{ $limits['radar_limit'] ?? 'Ilimitado' }} oportunidades</div></div>
                    <div class="limit"><strong>Projetos em elaboracao</strong><div class="muted">{{ $limits['draft_projects_limit'] ?? 'Ilimitado' }}</div></div>
                    <div class="limit"><strong>Monitoramentos ativos</strong><div class="muted">{{ $limits['active_monitoring_limit'] ?? 'Ilimitado' }}</div></div>
                    @if($planSlug === 'gratuito')<div class="notice">O plano gratuito permite descobrir oportunidades. Para analisar, preparar e acompanhar uma inscricao, contrate um plano pago.</div><a class="cta" href="{{ route('cultura.landing') }}#planos">Conhecer planos pagos</a>@endif
                </section>
                <section class="card" id="perfil" style="margin-top:18px">
                    <div class="section-title"><h2>Perfil cultural</h2></div>
                    @if($profile)<div class="muted">Perfil configurado. O ranking considera area cultural, perfil juridico, territorio, faixa financeira e prazo.</div><a class="cta" href="{{ route('cultura.profile.edit') }}">Editar perfil</a>@else<div class="notice">Complete seu perfil cultural para o Radar calcular a aderencia de cada oportunidade.</div><a class="cta" href="{{ route('cultura.profile.edit') }}">Configurar perfil</a>@endif
                </section>
            </aside>
        </div>
    </main>
</div>
</body>
</html>
