/* Build-time only: npm ci in this directory, then node build-legacy-richtext.cjs. */
const fs=require('node:fs'),path=require('node:path'),crypto=require('node:crypto');
const esbuild=require('esbuild');
const root=path.resolve(__dirname,'../..'),out=path.join(root,'admin/public/legacy-richtext');
const modules=path.dirname(path.dirname(require.resolve('turndown/package.json')));
const result=esbuild.buildSync({entryPoints:[path.join(__dirname,'legacy-converter.js')],nodePaths:[modules],bundle:true,minify:true,platform:'browser',outfile:path.join(out,'index.js'),metafile:true,legalComments:'eof'});
const used=new Set();
for(const file of Object.keys(result.metafile.inputs)){const p=file.split('node_modules/')[1];if(p)used.add(p.split('/')[0]);}
const packages=[];
for(const name of [...used].sort()){
 const dir=path.join(modules,name),info=JSON.parse(fs.readFileSync(path.join(dir,'package.json')));
 for(const file of fs.readdirSync(dir)){if(/^license/i.test(file)&&fs.statSync(path.join(dir,file)).isFile())fs.copyFileSync(path.join(dir,file),path.join(out,name+'-'+file));}
 packages.push({name:info.name,version:info.version,license:info.license});
}
fs.writeFileSync(path.join(out,'PROVENANCE.json'),JSON.stringify({registry:'https://registry.npmjs.org',packages,bundle_sha256:crypto.createHash('sha256').update(fs.readFileSync(path.join(out,'index.js'))).digest('hex'),source:'tools/vendor/legacy-converter.js',build:'npm ci --prefix tools/vendor && node tools/vendor/build-legacy-richtext.cjs'},null,2)+'\n');
