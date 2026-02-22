const canvas = document.getElementById("gameCanvas");
const ctx = canvas.getContext("2d");

let W, H;
function resize() {
    W = canvas.width = window.innerWidth;
    H = canvas.height = window.innerHeight;
}
window.addEventListener("resize", resize);
resize();

const CFG = {
    legStr: 0.8,
    armStrBase: 0.35,
    torsoStr: 0.25,
    fatigueRate: 0.002, 
    recoveryRate: 0.01, 
    gravity: 1.2,
    friction: 0.95
};

const keys = {};
window.addEventListener("keydown", e => {
    keys[e.code] = true;
    if (e.code === "ShiftLeft" || e.code === "ShiftRight") player.handleShift();
});
window.addEventListener("keyup", e => keys[e.code] = false);

/* ================= PHYSICS OBJECTS ================= */
class Prop {
    constructor(x, y) {
        this.x = x; this.y = y;
        this.oldX = x; this.oldY = y;
        this.isHeld = false;
        const isHeavy = Math.random() > 0.85;
        this.size = isHeavy ? 60 : 20 + Math.random() * 15;
        this.mass = this.size / 15; 
    }
    update() {
        if (this.isHeld) return;
        let vx = (this.x - this.oldX) * CFG.friction;
        let vy = (this.y - this.oldY) * CFG.friction;
        this.oldX = this.x; this.oldY = this.y;
        this.x += vx;
        this.y += vy + (CFG.gravity * this.mass);

        let gH = terrainLayers[terrainLayers.length - 1].getHeight(this.x); 
        if (this.y > gH - this.size/2) {
            this.y = gH - this.size/2;
            this.oldX = this.x + (this.oldX - this.x) * 0.5; 
        }
    }
    draw(cX, cY, isTargeted) {
        ctx.fillStyle = isTargeted ? "#fff" : (this.mass > 3 ? "#411" : "#1a1a1a");
        ctx.strokeStyle = "white";
        ctx.lineWidth = 2;
        ctx.strokeRect(this.x - cX - this.size/2, this.y - cY - this.size/2, this.size, this.size);
        ctx.fillRect(this.x - cX - this.size/2, this.y - cY - this.size/2, this.size, this.size);
    }
}

class Point {
    constructor(x, y) { this.x = x; this.y = y; this.oldX = x; this.oldY = y; }
    update() {
        let vx = (this.x - this.oldX) * CFG.friction;
        let vy = (this.y - this.oldY) * CFG.friction;
        this.oldX = this.x; this.oldY = this.y;
        this.x += vx;
        this.y += vy + CFG.gravity; 
    }
}

class Bone {
    constructor(p1, p2, len) { this.p1 = p1; this.p2 = p2; this.len = len; }
    resolve() {
        let dx = this.p2.x - this.p1.x, dy = this.p2.y - this.p1.y;
        let dist = Math.hypot(dx, dy);
        if (dist === 0) return;
        let diff = (this.len - dist) / dist;
        this.p1.x -= dx * diff * 0.5; this.p1.y -= dy * diff * 0.5;
        this.p2.x += dx * diff * 0.5; this.p2.y += dy * diff * 0.5;
    }
}

/* ================= SMOKE EFFECT (FRACTAL NOISE SIM) ================= */
class SmokeLayer {
    constructor(color, speed) {
        this.color = color;
        this.speed = speed;
        this.time = 0;
    }
    draw(camX, camY) {
        this.time += 0.01;
        ctx.save();
        ctx.globalAlpha = 0.3;
        ctx.fillStyle = this.color;
        
        // Simulating noise with three overlapping octaves
        for (let j = 0; j < 3; j++) {
            let offset = j * 50;
            ctx.beginPath();
            ctx.moveTo(0, H);
            for (let x = 0; x <= W; x += 30) {
                let noise = Math.sin(x * 0.002 + this.time + offset) * 20;
                noise += Math.sin(x * 0.005 - this.time * 0.5) * 10;
                let y = (H * 0.7) + noise - (camY * this.speed);
                ctx.lineTo(x, y);
            }
            ctx.lineTo(W, H);
            ctx.fill();
        }
        ctx.restore();
    }
}

/* ================= SCENERY ================= */
class DeadTree {
    constructor(x, y) {
        this.x = x; this.y = y;
        this.height = 60 + Math.random() * 120;
        this.branches = [];
        for(let i=0; i<5; i++) {
            this.branches.push({
                len: this.height * (0.3 + Math.random() * 0.4),
                angle: -Math.PI/2 + (Math.random() - 0.5) * 2,
                side: Math.random() > 0.5 ? 1 : -1
            });
        }
    }
    draw(cX, cY) {
        ctx.strokeStyle = "#111";
        ctx.lineWidth = 8;
        ctx.lineCap = "round";
        let sx = this.x - cX;
        let sy = this.y - cY;
        ctx.beginPath();
        ctx.moveTo(sx, sy);
        ctx.lineTo(sx, sy - this.height);
        ctx.stroke();
        ctx.lineWidth = 4;
        this.branches.forEach((b, i) => {
            let by = sy - (this.height * (0.2 + i*0.15));
            ctx.beginPath();
            ctx.moveTo(sx, by);
            ctx.lineTo(sx + Math.cos(b.angle) * b.len, by + Math.sin(b.angle) * b.len);
            ctx.stroke();
        });
    }
}

/* ================= PARALLAX SYSTEM ================= */
class TerrainLayer {
    constructor(seed, base, amp, color, speed, complexity, stroke, basementCfg = null) {
        this.seed = seed; this.base = base; this.amp = amp;
        this.color = color; this.speed = speed; this.complexity = complexity;
        this.stroke = stroke;
        this.basementCfg = basementCfg;
    }
    getHeight(x) {
        let v = Math.pow(Math.sin(x * 0.0003 + this.seed) * 0.5 + 0.5, 3.5);
        let m = Math.sin(x * 0.001 + this.seed) * this.amp;
        let h = Math.sin(x * 0.004 + this.seed) * (this.amp * 0.3);
        return H - this.base - (m + h) * (v * this.complexity);
    }
    drawBasements(camX, camY) {
        if (!this.basementCfg) return;
        const p = this.basementCfg;
        for (let i = -15; i < 30; i++) {
            let worldX = Math.floor((camX * this.speed) / p.spacing + i) * p.spacing;
            let seed = Math.abs(Math.sin(worldX + p.seed) * 10000);
            let width = (150 + (seed % 150)) * p.scale;
            let heightLimit = (200 + (seed % 500)) * p.scale;
            let isTriangle = (seed % 10 > 5);
            let screenX = worldX - camX * this.speed;
            let groundY = this.getHeight(worldX); 
            let screenY = groundY - heightLimit*0.3 - camY * this.speed;
            if (screenX > -width && screenX < W) {
                ctx.fillStyle = p.color;
                if (isTriangle) {
                    ctx.beginPath(); ctx.moveTo(screenX + width/2, screenY);
                    ctx.lineTo(screenX + width, screenY + heightLimit);
                    ctx.lineTo(screenX + width, H + 1000); ctx.lineTo(screenX, H + 1000);
                    ctx.lineTo(screenX, screenY + heightLimit); ctx.fill();
                } else {
                    ctx.fillRect(screenX, screenY, width, H + 1000);
                    ctx.fillStyle = p.windows;
                    let wSize = 10 * p.scale;
                    if (seed % 3 > 1) ctx.fillRect(screenX + width/4, screenY + 30, wSize, wSize*1.5);
                }
            }
        }
    }
    drawGrass(camX, camY) {
        if (this.speed < 1) return;
        ctx.strokeStyle = "#1a1a1a";
        ctx.lineWidth = 2;
        for (let x = 0; x < W; x += 40) {
            let worldX = x + camX;
            let seed = Math.abs(Math.sin(worldX * 0.01) * 100);
            if (seed > 99) {
                let h = terrainLayers[3].getHeight(worldX) - camY;
                ctx.beginPath(); ctx.moveTo(x, h); ctx.lineTo(x - 5, h - 15);
                ctx.moveTo(x, h); ctx.lineTo(x + 5, h - 12); ctx.stroke();
            }
        }
    }
    draw(camX, camY) {
        this.drawBasements(camX, camY);
        ctx.fillStyle = this.color;
        if (this.stroke) { ctx.strokeStyle = this.stroke; ctx.lineWidth = 4; }
        ctx.beginPath(); ctx.moveTo(0, H);
        for (let x = 0; x <= W; x += 15) {
            ctx.lineTo(x, this.getHeight(x + camX * this.speed) - camY * this.speed);
        }
        ctx.lineTo(W, H); ctx.fill();
        if (this.stroke) ctx.stroke();
        this.drawGrass(camX, camY);
    }
}

/* ================= INITIALIZATION ================= */
const terrainLayers = [
    new TerrainLayer(100, 500, 600, "#2c2c44", 0.15, 0.7, "#3a3a5a", { seed: 1000, color: "#25253d", spacing: 600, scale: 0.5, windows: "#3a3a5a" }),
    new TerrainLayer(123, 350, 450, "#1a1a2e", 0.4, 1.2, "#252540", { seed: 3500, color: "#121225", spacing: 500, scale: 0.75, windows: "#25254a" }),
    new TerrainLayer(456, 200, 300, "#0d0d1a", 0.7, 2.0, "#151525", { seed: 5000, color: "#080815", spacing: 400,  scale: 1.1, windows: "#4a4a7d" }),
    new TerrainLayer(789, 100, 250, "#000000", 1.0, 3.5, "#ffffff") 
];

const smokePlanes = [
    new SmokeLayer("rgba(100, 100, 150, 0.4)", 0.2),
    new SmokeLayer("rgba(50, 50, 80, 0.3)", 0.5)
];

const props = [];
for(let i=0; i<45; i++) {
    let sx = Math.random() * 12000;
    props.push(new Prop(sx, terrainLayers[3].getHeight(sx) - 100));
}

const trees = [];
for(let i=0; i<30; i++) {
    let tx = Math.random() * 12000;
    trees.push(new DeadTree(tx, terrainLayers[3].getHeight(tx)));
}

// Stickman code (kept for completeness)
class Stickman {
    constructor() {
        this.x = 200; this.y = 200; this.vx = 0; this.facing = 1; this.run = 0;
        this.onGround = false; this.heldItem = null; this.throwTimer = 0; this.tiredness = 0;
        this.hip = new Point(this.x, this.y);
        this.head = new Point(this.x, this.y - 65);
        this.neck = new Point(this.x, this.y - 50);
        this.kneeL = new Point(this.x, this.y + 35);
        this.ankleL = new Point(this.x, this.y + 70);
        this.kneeR = new Point(this.x, this.y + 35);
        this.ankleR = new Point(this.x, this.y + 70);
        this.elbowL = new Point(this.x, this.y - 25);
        this.handL = new Point(this.x, this.y);
        this.elbowR = new Point(this.x, this.y - 25);
        this.handR = new Point(this.x, this.y);
        this.points = [this.hip, this.head, this.neck, this.kneeL, this.ankleL, this.kneeR, this.ankleR, this.elbowL, this.handL, this.elbowR, this.handR];
        this.bones = [
            new Bone(this.hip, this.neck, 45), new Bone(this.neck, this.head, 18),
            new Bone(this.hip, this.kneeL, 38), new Bone(this.kneeL, this.ankleL, 38),
            new Bone(this.hip, this.kneeR, 38), new Bone(this.kneeR, this.ankleR, 38),
            new Bone(this.neck, this.elbowL, 26), new Bone(this.elbowL, this.handL, 26),
            new Bone(this.neck, this.elbowR, 26), new Bone(this.elbowR, this.handR, 26)
        ];
    }
    handleShift() {
        if (this.heldItem) { if (this.throwTimer === 0) this.throwTimer = 1; } 
        else if (this.targetProp) { this.heldItem = this.targetProp; this.heldItem.isHeld = true; }
    }
    update() {
        let gH = terrainLayers[3].getHeight(this.x);
        let speed = Math.abs(this.vx);
        this.onGround = (this.hip.y >= gH - 85);
        if (this.heldItem) {
            this.tiredness += CFG.fatigueRate * this.heldItem.mass;
            if (this.tiredness > 1) { this.heldItem.isHeld = false; this.heldItem = null; }
        } else {
            this.tiredness -= CFG.recoveryRate;
            if (this.tiredness < 0) this.tiredness = 0;
        }
        let accel = this.onGround ? 0.4 : 0.2;
        if (keys.ArrowLeft) { this.vx -= accel; this.facing = -1; }
        if (keys.ArrowRight) { this.vx += accel; this.facing = 1; }
        this.vx *= 0.95; this.x += this.vx;
        if (keys.Space && this.onGround) { this.hip.oldY = this.hip.y + 8; this.onGround = false; }
        this.run = this.x * 0.015;
        if (this.onGround) { this.y = gH - 75; this.hip.x = this.x; this.hip.y = this.y; }
        this.targetProp = null;
        let minDist = 140;
        props.forEach(p => {
            let d = Math.hypot(p.x - this.x, p.y - this.hip.y);
            if (d < minDist && !p.isHeld) { minDist = d; this.targetProp = p; }
        });
        let stride = 50 + speed * 3.2, lift = 2 + speed * 1.5;
        let tALX = this.x + Math.sin(this.run) * stride, tALY = terrainLayers[3].getHeight(tALX) - Math.max(0, Math.cos(this.run) * lift);
        let tARX = this.x + Math.sin(this.run + Math.PI) * stride, tARY = terrainLayers[3].getHeight(tARX) - Math.max(0, Math.cos(this.run + Math.PI) * lift);
        let tHLX, tHLY, tHRX, tHRY, nTX = this.x, nTY = this.hip.y - 50;
        if (this.throwTimer > 0) {
            this.throwTimer++;
            if (this.throwTimer < 10) { tHRX = this.x - this.facing * 40; tHRY = this.neck.y + 20; }
            else { 
                tHRX = this.x + this.facing * 60; tHRY = this.neck.y - 70;
                if (this.throwTimer === 12 && this.heldItem) {
                    let rx = (this.handR.x - this.handR.oldX) * 2.5, ry = (this.handR.y - this.handR.oldY) * 2.5;
                    this.heldItem.isHeld = false; this.heldItem.oldX = this.heldItem.x - (rx + 15 * this.facing);
                    this.heldItem.oldY = this.heldItem.y - (ry - 10); this.heldItem = null;
                }
            }
            if (this.throwTimer > 20) this.throwTimer = 0;
            tHLX = this.x - this.facing * 10; tHLY = this.neck.y + 30;
        } else if (this.heldItem) {
            let shake = this.tiredness * Math.sin(Date.now() * 0.05) * 6;
            tHLX = tHRX = this.x + this.facing * 25; tHLY = tHRY = this.neck.y + 15 + (this.heldItem.mass * 12) + (this.tiredness * 45) + shake;
        } else if (this.targetProp) {
            tHLX = tHRX = this.targetProp.x; tHLY = tHRY = this.targetProp.y;
            nTX = this.x + (this.targetProp.x - this.x) * 0.5; nTY = this.hip.y - 15;
        } else {
            tHLX = this.x - Math.sin(this.run) * (stride * 0.8); tHLY = this.neck.y + 25 + Math.cos(this.run) * 10;
            tHRX = this.x - Math.sin(this.run + Math.PI) * (stride * 0.8); tHRY = this.neck.y + 25 + Math.cos(this.run + Math.PI) * 10;
        }
        const legS = this.onGround ? CFG.legStr : 0.15;
        const armS = (CFG.armStrBase / (this.heldItem ? this.heldItem.mass : 1)) * (1 - this.tiredness * 0.7);
        [this.ankleL, this.ankleR].forEach((p, i) => { p.x += ([tALX, tARX][i] - p.x) * legS; p.y += ([tALY, tARY][i] - p.y) * legS; });
        [this.handL, this.handR].forEach((p, i) => { p.x += ([tHLX, tHRX][i] - p.x) * armS; p.y += ([tHLY, tHRY][i] - p.y) * armS; });
        this.kneeL.x += this.facing * 15 * legS; this.kneeR.x += this.facing * 15 * legS;
        this.neck.x += (nTX - this.neck.x) * CFG.torsoStr; this.neck.y += (nTY - this.neck.y) * CFG.torsoStr;
        this.points.forEach(p => { p.update(); let h = terrainLayers[3].getHeight(p.x); if (p.y > h) p.y = h; });
        for (let i = 0; i < 20; i++) this.bones.forEach(b => b.resolve());
        if (this.onGround && !this.targetProp) { this.head.x = this.neck.x; this.head.y = this.neck.y - 18; }
        else if (this.targetProp) {
            let hA = Math.atan2(this.targetProp.y - this.neck.y, this.targetProp.x - this.neck.x);
            this.head.x = this.neck.x + Math.cos(hA) * 18; this.head.y = this.neck.y + Math.sin(hA) * 18;
        }
        if (this.heldItem) { this.heldItem.x = this.handR.x; this.heldItem.y = this.handR.y; }
    }
    draw(cX, cY) {
        ctx.strokeStyle = "white"; ctx.lineWidth = 10; ctx.lineCap = "round";
        this.bones.forEach(b => { ctx.beginPath(); ctx.moveTo(b.p1.x - cX, b.p1.y - cY); ctx.lineTo(b.p2.x - cX, b.p2.y - cY); ctx.stroke(); });
        ctx.fillStyle = "white"; ctx.beginPath(); ctx.arc(this.head.x - cX, this.head.y - cY, 15, 0, Math.PI * 2); ctx.fill();
    }
}

const player = new Stickman();
let camX = 0, camY = 0;

function loop() {
    ctx.fillStyle = "#3a3a5a"; ctx.fillRect(0, 0, W, H);
    camX += (player.x - W / 2 - camX) * 0.1;
    camY += (player.hip.y - H / 1.5 - camY) * 0.05;

    // INTERWEAVED RENDERING
    terrainLayers[0].draw(camX, camY);
    smokePlanes[0].draw(camX, camY); // Smoke between far mountains
    
    terrainLayers[1].draw(camX, camY);
    terrainLayers[2].draw(camX, camY);
    smokePlanes[1].draw(camX, camY); // Thicker smoke in mid-ground
    
    terrainLayers[3].draw(camX, camY);
    
    trees.forEach(t => t.draw(camX, camY));
    props.forEach(p => {
        p.update();
        p.draw(camX, camY, Math.hypot(p.x - player.x, p.y - player.hip.y) < 140 && !player.heldItem);
    });

    player.update();
    player.draw(camX, camY);
    requestAnimationFrame(loop);
}
loop();
