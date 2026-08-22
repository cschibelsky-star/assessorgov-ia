<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Perfil Cultural | AssessorGov Cultura</title>
    <style>
        :root{--bg:#07111f;--panel:#0d1b2d;--line:#203550;--text:#edf5ff;--muted:#9fb2c9;--accent:#7c5cff;--success:#31c48d}
        *{box-sizing:border-box} body{margin:0;font-family:Inter,system-ui,Arial;background:linear-gradient(180deg,#06101d,#0a1525);color:var(--text)}
        .shell{max-width:980px;margin:auto;padding:28px 18px 60px}.top{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px}.brand{font-weight:800;font-size:20px}.brand span{color:#a996ff}.back{color:var(--muted);text-decoration:none}
        .card{background:rgba(13,27,45,.92);border:1px solid var(--line);border-radius:18px;padding:24px}.lead{color:var(--muted);margin-top:6px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-top:22px}.full{grid-column:1/-1}
        label{display:block;font-size:13px;color:#b8c7d9;margin-bottom:7px;font-weight:700} input,select{width:100%;padding:12px 13px;border-radius:11px;border:1px solid #2a4261;background:#091523;color:#fff;outline:none} input:focus,select:focus{border-color:#7c5cff}
        .choices{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:9px}.choice{border:1px solid #2a4261;border-radius:10px;padding:10px;background:#091523}.choice input{width:auto;margin-right:7px}
        .actions{display:flex;gap:12px;justify-content:flex-end;margin-top:24px}.btn{border:0;border-radius:11px;padding:12px 18px;font-weight:800;cursor:pointer}.primary{background:var(--accent);color:#fff}.secondary{background:#13253a;color:#d7e5f5;text-decoration:none}.error{background:#3b1822;border:1px solid #7c2d3b;padding:12px;border-radius:10px;margin:14px 0;color:#ffd9df}
        @media(max-width:720px){.grid,.choices{grid-template-columns:1fr}.full{grid-column:auto}.actions{flex-direction:column}.btn{text-align:center}}
    </style>
</head>
<body>
<div class="shell">
    <div class="top"><div class="brand">AssessorGov <span>Cultura</span></div><a class="back" href="{{ route('cultura.dashboard') }}">← Voltar ao painel</a></div>
    <div class="card">
        <h1 style="margin:0">Seu Perfil Cultural</h1>
        <p class="lead">Quanto melhor o perfil, mais preciso será o Radar Cultural SP. Use informações que representem sua atuação real.</p>
        @if($errors->any())<div class="error">Revise os campos destacados antes de salvar.</div>@endif
        <form method="POST" action="{{ route('cultura.profile.update') }}">
            @csrf @method('PUT')
            <div class="grid">
                <div><label>Nome artístico / nome de atuação</label><input name="display_name" value="{{ old('display_name',$profile?->display_name) }}"></div>
                <div><label>Município principal *</label><input name="municipality" required value="{{ old('municipality',$profile?->municipality) }}" placeholder="Ex.: Sumaré"></div>
                <div><label>Forma de participação *</label><select name="legal_profiles[]" required><option value="">Selecione</option>@foreach(['Pessoa Física','MEI','Pessoa Jurídica','Coletivo','OSC'] as $item)<option value="{{ $item }}" @selected(in_array($item, old('legal_profiles',$profile?->legal_profiles ?? [])))>{{ $item }}</option>@endforeach</select></div>
                <div><label>Anos de experiência</label><input type="number" min="0" max="99" name="experience_years" value="{{ old('experience_years',$profile?->experience_years) }}"></div>
                <div class="full"><label>Áreas culturais *</label><div class="choices">@foreach(['Música','Artes Cênicas','Dança','Audiovisual','Literatura','Artes Visuais','Cultura Popular','Patrimônio','Circo'] as $area)<label class="choice"><input type="checkbox" name="cultural_areas[]" value="{{ $area }}" @checked(in_array($area, old('cultural_areas',$profile?->cultural_areas ?? [])))>{{ $area }}</label>@endforeach</div></div>
                <div><label>Faixa mínima desejada (R$)</label><input type="number" step="0.01" min="0" name="preferred_budget_min" value="{{ old('preferred_budget_min',$profile?->preferred_budget_min) }}"></div>
                <div><label>Faixa máxima desejada (R$)</label><input type="number" step="0.01" min="0" name="preferred_budget_max" value="{{ old('preferred_budget_max',$profile?->preferred_budget_max) }}"></div>
                <div class="full"><label>Territórios de atuação</label><input name="territories[]" value="{{ old('territories.0',$profile?->territories[0] ?? '') }}" placeholder="Ex.: Região Metropolitana de Campinas"></div>
                <div class="full"><label>Públicos prioritários</label><input name="audiences[]" value="{{ old('audiences.0',$profile?->audiences[0] ?? '') }}" placeholder="Ex.: crianças, juventude, pessoas com deficiência"></div>
            </div>
            <div class="actions"><a class="btn secondary" href="{{ route('cultura.dashboard') }}">Cancelar</a><button class="btn primary" type="submit">Salvar Perfil Cultural</button></div>
        </form>
    </div>
</div>
</body>
</html>
