/* ── AMBIENT CANVAS BACKGROUND PHYSICS ── */
(function(){
    const canvas=document.getElementById('particles'),ctx=canvas.getContext('2d');
    let W,H,dots=[];
    function resize(){ W=canvas.width=window.innerWidth; H=canvas.height=window.innerHeight; }
    function makeDot(){ return{x:Math.random()*W,y:Math.random()*H,r:Math.random()*1.2+0.3,vx:(Math.random()-.5)*.18,vy:(Math.random()-.5)*.18,alpha:Math.random()*.35+.05}; }
    resize(); for(let i=0;i<70;i++) dots.push(makeDot()); window.addEventListener('resize',resize);
    function draw(){
        ctx.clearRect(0,0,W,H);
        dots.forEach(d=>{ d.x+=d.vx; d.y+=d.vy; if(d.x<0)d.x=W; if(d.x>W)d.x=0; if(d.y<0)d.y=H; if(d.y>H)d.y=0;
            ctx.beginPath(); ctx.arc(d.x,d.y,d.r,0,Math.PI*2); ctx.fillStyle=`rgba(231,192,140,${d.alpha})`; ctx.fill(); });
        requestAnimationFrame(draw);
    } draw();
})();

/* ── TAB SELECTOR LAYER ── */
function switchTab(tab){
    document.getElementById('viewSignin').classList.toggle('active', tab==='signin');
    document.getElementById('viewSignup').classList.toggle('active', tab==='signup');
    document.getElementById('tabSignin').classList.toggle('active', tab==='signin');
    document.getElementById('tabSignup').classList.toggle('active', tab==='signup');
    clearErrors();
}
function clearErrors(){
    ['si-error','su-error'].forEach(id=>{ const el=document.getElementById(id); if(el) el.textContent=''; });
}

/* ── INPUT MASK CONTROLS ── */
function toggleEye(inputId, openId, closedId){
    const inp=document.getElementById(inputId);
    const shown=inp.type==='text';
    inp.type=shown?'password':'text';
    document.getElementById(openId).style.display=shown?'':'none';
    document.getElementById(closedId).style.display=shown?'none':'';
}

/* ── COMPLEXITY METRIC ENGINE ── */
const pwdRules = {
    'r-len':   v=>v.length>=8,
    'r-upper': v=>/[A-Z]/.test(v),
    'r-lower': v=>/[a-z]/.test(v),
    'r-num':   v=>/[0-9]/.test(v),
    'r-sym':   v=>/[^A-Za-z0-9]/.test(v),
    'r-long':  v=>v.length>=16,
};
const COLORS=['#ef4444','#f97316','#eab308','#22c55e','#10b981','#6366f1'];
const LABELS=['Very Weak','Weak','Fair','Good','Strong','Very Strong'];

function onPwdInput(val){
    const wrap=document.getElementById('su-strengthWrap');
    const bar =document.getElementById('su-strengthBar');
    const lbl =document.getElementById('su-strengthLabel');
    if(!val){ wrap.style.display='none'; return; }
    wrap.style.display='block';
    let score=0;
    Object.entries(pwdRules).forEach(([id,test])=>{
        const el=document.getElementById(id);
        const ok=test(val);
        el.classList.toggle('met',ok);
        if(ok) score++;
    });
    const idx=Math.min(score,5);
    bar.style.width=Math.round((score/6)*100)+'%';
    bar.style.backgroundColor=COLORS[idx];
    lbl.style.color=COLORS[idx];
    lbl.textContent=LABELS[idx];
    const conf=document.getElementById('su-confirm').value;
    if(conf) onConfirmInput(conf);
}

function onConfirmInput(val){
    const pwd=document.getElementById('su-pwd').value;
    const note=document.getElementById('su-match');
    if(!val){ note.textContent=''; return; }
    if(val===pwd){
        note.style.color='#4ade80'; note.textContent='✓ Passwords match';
        document.getElementById('su-confirm').className = 'ok-state';
    } else {
        note.style.color='#ef4444'; note.textContent='✗ Passwords do not match';
        document.getElementById('su-confirm').className = 'error-state';
    }
}

/* ── ASYNCHRONOUS NETWORK DISPATCHERS (AJAX FETCH) ── */
function doSignIn(){
    const email = document.getElementById('si-email').value.trim();
    const pwd   = document.getElementById('si-pwd').value;
    const errEl = document.getElementById('si-error');
    const btn   = document.getElementById('si-btn');
    const emailRx = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    if(!email || !pwd){ errEl.textContent = 'All fields are required.'; return; }
    if(!emailRx.test(email)){ errEl.textContent = 'Enter a valid email address.'; return; }

    btn.classList.add('loading'); btn.disabled = true; errEl.textContent = '';

    fetch('https://suharshith.infinityfreeapp.com/auth.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'signup', name: name, email: email, mobile: mobile, pwd: pwd, confirm: confirm })
    })
    .then(r => r.json())
    .then(data => {
        btn.classList.remove('loading'); btn.disabled = false;
        if(data.success){
            showSuccess(`Access Granted`, `Authenticated as ${data.name}. Loading dashboard matrix…`);
        } else {
            errEl.textContent = data.message;
        }
    })
    .catch(() => {
        btn.classList.remove('loading'); btn.disabled = false;
        errEl.textContent = 'Network communication failure. Check server.';
    });
}

function doSignUp(){
    const name    = document.getElementById('su-name').value.trim();
    const email   = document.getElementById('su-email').value.trim();
    const mobile  = document.getElementById('su-mobile').value.trim();
    const pwd     = document.getElementById('su-pwd').value;
    const confirm = document.getElementById('su-confirm').value;
    const errEl   = document.getElementById('su-error');
    const btn     = document.getElementById('su-btn');
    
    const emailRx  = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    const mobileRx = /^[0-9]{10}$/; // Strict 10-digit expression match filter

    if(!name || !email || !mobile || !pwd || !confirm){ errEl.textContent = 'All fields are required.'; return; }
    if(!emailRx.test(email)){ errEl.textContent = 'Enter a valid email address.'; return; }
    if(!mobileRx.test(mobile)){ errEl.textContent = 'Enter a valid 10-digit mobile number.'; return; }
    if(pwd.length < 8){ errEl.textContent = 'Password must be at least 8 characters.'; return; }
    if(pwd !== confirm){ errEl.textContent = 'Passwords do not match.'; return; }

    btn.classList.add('loading'); btn.disabled = true; errEl.textContent = '';

    fetch('https://suharshith.infinityfreeapp.com/auth.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'signup', name: name, email: email, mobile: mobile, pwd: pwd, confirm: confirm })
    })
    .then(r => r.json())
    .then(data => {
        btn.classList.remove('loading'); btn.disabled = false;
        if(data.success) {
            document.getElementById('si-email').value = email;
            document.getElementById('si-pwd').value = '';
            switchTab('signin');
            const siErr = document.getElementById('si-error');
            siErr.style.color = '#4ade80';
            siErr.textContent = 'Registration synced! Please log in below.';
        } else {
            errEl.textContent = data.message;
        }
    })
    .catch(() => {
        btn.classList.remove('loading'); btn.disabled = false;
        errEl.textContent = 'Communication failure. Validate deployment state.';
    });
}

function showSuccess(title, sub){
    document.getElementById('overlayTitle').textContent = title;
    document.getElementById('overlaySub').textContent = sub;
    document.getElementById('successOverlay').classList.add('show');
    requestAnimationFrame(() => { requestAnimationFrame(() => { document.getElementById('redirectFill').style.width = '100%'; }); });
    
    // Redirects directly to the new internal PHP dashboard
    setTimeout(() => { window.location.href = 'dashboard.php'; }, 2500);
}

document.addEventListener('input', e => { if(e.target.tagName === 'INPUT') e.target.classList.remove('error-state'); });